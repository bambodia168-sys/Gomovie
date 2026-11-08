<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private function model(string $type): string
    {
        abort_unless(in_array($type, ['movies', 'series'], true), 404);
        return $type === 'movies' ? Movie::class : Series::class;
    }

    public function index(Request $request, string $type): View
    {
        $model = $this->model($type);
        $data = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['draft', 'published', 'archived'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 30, 40, 50])],
        ]);
        $perPage = (int) ($data['per_page'] ?? 10);

        return view('admin.catalog.index', [
            'type' => $type,
            'perPage' => $perPage,
            'titles' => $model::query()
                ->when(trim((string) ($data['q'] ?? '')) !== '', fn ($query) =>
                    $query->where('title', 'like', '%'.trim($data['q']).'%'))
                ->when($data['status'] ?? null, fn ($query, $value) =>
                    $query->where('status', $value))
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate($perPage)->withQueryString(),
        ]);
    }


    /**
     * Drafts remain private. Staff preview never bypasses the public publication
     * scope or enables access to streaming manifests.
     */
    public function preview(string $type, int $id): View
    {
        $model = $this->model($type);
        $query = $model::query();

        if ($type === 'series') {
            $query->with(['seasons' => fn ($seasons) =>
                $seasons->orderBy('season_number')->with([
                    'episodes' => fn ($episodes) => $episodes->orderBy('episode_number'),
                ])]);
        }

        return view('admin.catalog.preview', [
            'type' => $type,
            'title' => $query->findOrFail($id),
        ]);
    }

    /**
     * Bulk changes are limited to explicitly selected IDs of a single content
     * type. They never create or change media licenses/entitlements.
     */
    public function bulk(Request $request, string $type): RedirectResponse
    {
        $model = $this->model($type);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'action' => ['required', Rule::in(['draft', 'published'])],
            'approve_metadata' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['draft', 'published', 'archived'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10,20,30,40,50])],
        ]);

        if ($data['action'] === 'published' && !($data['approve_metadata'] ?? false)) {
            throw ValidationException::withMessages([
                'approve_metadata' => 'Confirm metadata, artwork rights and attribution before publishing.',
            ]);
        }

        $ids = array_map('intval', $data['ids']);
        $changed = DB::transaction(function () use ($request, $model, $type, $data, $ids): int {
            $titles = $model::query()->whereIn('id', $ids)->lockForUpdate()->get();
            if ($titles->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => 'Some selected titles no longer exist. Refresh and choose again.',
                ]);
            }

            $count = 0;
            foreach ($titles as $title) {
                if ($title->status === $data['action']) {
                    continue;
                }
                $title->forceFill([
                    'status' => $data['action'],
                    'published_at' => $data['action'] === 'published' ? now() : null,
                ])->save();
                $this->audit($request, 'catalog.bulk.'.$data['action'], $type, $title->id);
                $count++;
            }
            return $count;
        });

        $query = array_filter([
            'q' => $data['q'] ?? null,
            'status' => $data['status'] ?? null,
            'per_page' => $data['per_page'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return redirect()->route('admin.catalog.index', array_merge(['type' => $type], $query))
            ->with('status', $changed.' '.($changed === 1 ? 'title' : 'titles')
                .' changed to '.ucfirst($data['action']).'.');
    }

    /** "Remove" archives a title safely, retaining episodes, audit and rights. */
    public function archive(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
        $model = $this->model($type);
        $title = $model::query()->findOrFail($id);

        DB::transaction(function () use ($request, $title, $type): void {
            $title->forceFill(['status' => 'archived', 'published_at' => null])->save();
            $this->audit($request, 'catalog.archived', $type, $title->id);
        });

        return redirect()->route('admin.catalog.index', ['type' => $type])
            ->with('status', 'Title removed from public catalog (archived). No records were deleted.');
    }

    public function create(string $type): View
    {
        $this->model($type);
        return view('admin.catalog.form', ['type' => $type, 'title' => null]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $model = $this->model($type);
        $data = $this->validateTitle($request, $type);
        $created = DB::transaction(function () use ($request,$model,$data,$type) {
            $title = $model::query()->create($data);
            $this->audit($request,'catalog.created',$type,$title->id);
            return $title;
        });

        return redirect()->route('admin.catalog.edit',[$type,$created->id])
            ->with('status','Title created. Confirm distribution rights before making media available.');
    }

    public function edit(string $type, int $id): View
    {
        $model = $this->model($type);
        return view('admin.catalog.form', [
            'type' => $type,
            'title' => $model::query()->findOrFail($id),
        ]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $model = $this->model($type);
        $title = $model::query()->findOrFail($id);
        $data = $this->validateTitle($request,$type,$id);
        DB::transaction(function () use ($title,$data,$request,$type): void {
            $title->update($data);
            $this->audit($request,'catalog.updated',$type,$title->id);
        });

        return back()->with('status','Title updated.');
    }

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($request->user()->role,['admin','super_admin'],true),403);
        $model = $this->model($type);
        $title = $model::query()->findOrFail($id);
        abort_unless($title->status === 'draft', 422, 'Archive published content rather than delete it.');
        abort_if(DB::table('content_rights')->where('licensable_type',$type === 'movies' ? 'movie' : 'series')
            ->where('licensable_id',$id)->exists(), 422, 'Cannot delete content with rights records.');
        if ($type === 'movies') {
            abort_if($title->mediaAssets()->exists(),422,'Cannot delete titles with media.');
        } else {
            abort_if($title->seasons()->exists(),422,'Cannot delete series with seasons.');
        }

        DB::transaction(function () use ($title,$request,$type): void {
            $id = $title->id;
            $title->delete();
            $this->audit($request,'catalog.deleted',$type,$id);
        });
        return redirect()->route('admin.catalog.index',$type)->with('status','Draft deleted.');
    }

    private function validateTitle(Request $request,string $type,?int $id=null): array
    {
        $model = $this->model($type);
        $table = $type === 'movies' ? 'movies' : 'series';
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'slug' => ['nullable','string','max:255','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',Rule::unique($table,'slug')->ignore($id)],
            'overview' => ['nullable','string','max:12000'],
            'tmdb_id' => ['nullable','integer','min:1',Rule::unique($table,'tmdb_id')->ignore($id)],
            'country_code' => ['nullable','string','size:2','alpha'],
            'poster_path' => ['nullable','string','max:1024'],
            'backdrop_path' => ['nullable','string','max:1024'],
            'is_premium' => ['required','boolean'],
            'status' => ['required',Rule::in(['draft','published','archived'])],
            'release_year' => [$type === 'movies' ? 'required' : 'nullable','integer','between:1888,2100'],
            'runtime_minutes' => ['nullable','integer','min:1','max:1440'],
            'first_air_date' => ['nullable','date_format:Y-m-d'],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        if ($data['slug'] === '') {
            throw ValidationException::withMessages(['slug'=>'Enter a title that produces a valid URL slug.']);
        }
        if ($model::query()->where('slug',$data['slug'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists()) {
            throw ValidationException::withMessages(['slug'=>'The URL slug is already in use.']);
        }
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;
        if ($type === 'movies') unset($data['first_air_date']);
        else unset($data['release_year'],$data['runtime_minutes']);

        // Metadata publishing never confers streaming rights.
        $data['published_at'] = $data['status']==='published' ? now() : null;

        return $data;
    }

    private function audit(Request $request,string $action,string $type,int $id): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id'=>$request->user()->id,'action'=>$action,'resource_type'=>$type,
            'resource_id'=>$id,'details'=>null,'ip_address'=>$request->ip(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
