<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\StudioAppearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AppearanceController extends Controller
{
    private function guard(Request $request, bool $write = false): void
    {
        abort_unless($request->user() && in_array($request->user()->role, ['admin','super_admin'], true), 403);
        if ($write) abort_unless($request->user()->role === 'super_admin', 403);
    }

    public function index(Request $request): View
    {
        $this->guard($request);
        $query = DB::table('studio_pages')->where('is_custom', true);
        if ($request->filled('q')) {
            $term = Str::limit(trim((string) $request->input('q')), 80, '');
            $query->where(fn ($q) => $q->where('slug','like','%'.$term.'%')
                ->orWhere('heading','like','%'.$term.'%'));
        }
        $slotRows = collect(StudioAppearance::SLOTS)
            ->map(fn (array $item, string $key) => ['key'=>$key] + $item)
            ->values();
        $page=max(1,(int)$request->query('sections_page',1));
        $editableSlots=new LengthAwarePaginator(
            $slotRows->forPage($page,10)->values(),$slotRows->count(),10,$page,
            ['path'=>$request->url(),'query'=>$request->query(),'pageName'=>'sections_page']
        );

        return view('admin.appearance.index', [
            'editableSlots'=>$editableSlots,
            'slotOverrides'=>DB::table('studio_content_slots')->get()->keyBy('key'),
            'builtIns' => StudioAppearance::PAGES,
            'overrides' => DB::table('studio_pages')->where('is_custom',false)->get()->keyBy('slug'),
            'customPages' => $query->orderByDesc('id')->paginate(10)->withQueryString(),
            'typography' => StudioAppearance::typography(),
            'canEdit' => $request->user()->role === 'super_admin',
        ]);
    }

    private function fields(Request $request, bool $custom): array
    {
        return $request->validate([
            'eyebrow' => ['nullable','string','max:120'],
            'heading' => [$custom?'required':'nullable','string','max:180'],
            'description' => ['nullable','string','max:2000'],
            'body' => ['nullable','string','max:16000'],
            'seo_title' => ['nullable','string','max:160'],
            'seo_description' => ['nullable','string','max:300'],
            'is_published' => ['sometimes','boolean'],
        ]);
    }

    private function audit(Request $request, string $action, int $id, array $changes, string $resourceType='appearance_page'): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id'=>$request->user()->id, 'action'=>$action,
            'resource_type'=>$resourceType,'resource_id'=>$id,
            'details'=>json_encode($changes,JSON_THROW_ON_ERROR),
            'ip_address'=>$request->ip(), 'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request,true);
        $request->validate(['slug'=>['required','string','max:90','regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D',
            Rule::notIn(array_keys(StudioAppearance::PAGES)),Rule::unique('studio_pages','slug')]]);
        $fields=$this->fields($request,true);
        $id=DB::transaction(function () use ($request,$fields): int {
            $id=DB::table('studio_pages')->insertGetId([
                'slug'=>$request->input('slug'), 'is_custom'=>true,
                'is_published'=>(bool)($fields['is_published']??false),
                'eyebrow'=>$fields['eyebrow']??null,'heading'=>$fields['heading'],
                'description'=>$fields['description']??null, 'body'=>$fields['body']??null,
                'seo_title'=>$fields['seo_title']??null, 'seo_description'=>$fields['seo_description']??null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit($request,'appearance.page.created',$id,['slug'=>$request->input('slug')]);
            return $id;
        });
        return redirect()->route('admin.appearance.index')
            ->with('status','New page created '.($fields['is_published']??false?'and published.':'as draft.'));
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $this->guard($request,true);
        $builtIn=array_key_exists($slug,StudioAppearance::PAGES);
        $record=DB::table('studio_pages')->where('slug',$slug)->first();
        abort_unless($builtIn || $record?->is_custom,404);
        $fields=$this->fields($request,!$builtIn);
        DB::transaction(function () use ($request,$record,$builtIn,$slug,$fields): void {
            $values=[
                'eyebrow'=>$fields['eyebrow']??null,'heading'=>$fields['heading']??null,
                'description'=>$fields['description']??null,'body'=>$fields['body']??null,
                'seo_title'=>$fields['seo_title']??null,
                'seo_description'=>$fields['seo_description']??null,
                'is_published'=>$builtIn?true:(bool)($fields['is_published']??false),
                'updated_at'=>now(),
            ];
            if ($record) {
                DB::table('studio_pages')->where('id',$record->id)->update($values);
                $id=$record->id;
            } else {
                $id=DB::table('studio_pages')->insertGetId($values+[
                    'slug'=>$slug,'is_custom'=>false,'created_at'=>now(),
                ]);
            }
            $this->audit($request,'appearance.page.updated',$id,['slug'=>$slug]);
        });
        return back()->with('status','Page appearance saved.');
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $this->guard($request,true);
        $row=DB::table('studio_pages')->where('slug',$slug)->firstOrFail();
        DB::transaction(function () use ($request,$row): void {
            DB::table('studio_pages')->where('id',$row->id)->delete();
            $this->audit($request, $row->is_custom?'appearance.page.deleted':'appearance.page.reset',
                $row->id,['slug'=>$row->slug]);
        });
        return back()->with('status',$row->is_custom?'Custom page deleted.':'Page restored to defaults.');
    }

    public function saveSlot(Request $request, string $slot): RedirectResponse
    {
        $this->guard($request,true);
        abort_unless(array_key_exists($slot,StudioAppearance::SLOTS),404);
        $values=$request->validate(['content'=>['required','string','max:300']]);
        $content=trim($values['content']);
        if ($content==='') return back()->withErrors(['content'=>'Enter visible text for this element.']);

        DB::transaction(function () use ($request,$slot,$content): void {
            $record=DB::table('studio_content_slots')->where('key',$slot)->first();
            if ($record) {
                DB::table('studio_content_slots')->where('id',$record->id)->update([
                    'content'=>$content,'updated_by'=>$request->user()->id,'updated_at'=>now(),
                ]);
                $id=$record->id;
            } else {
                $id=DB::table('studio_content_slots')->insertGetId([
                    'key'=>$slot,'content'=>$content,'updated_by'=>$request->user()->id,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            $this->audit($request,'appearance.slot.updated',$id,['slot'=>$slot], 'appearance_slot');
        });

        return back()->with('status','Frontend text saved.');
    }

    public function resetSlot(Request $request, string $slot): RedirectResponse
    {
        $this->guard($request,true);
        abort_unless(array_key_exists($slot,StudioAppearance::SLOTS),404);

        DB::transaction(function () use ($request,$slot): void {
            $record=DB::table('studio_content_slots')->where('key',$slot)->first();
            if (!$record) return;
            DB::table('studio_content_slots')->where('id',$record->id)->delete();
            $this->audit($request,'appearance.slot.reset',$record->id,['slot'=>$slot], 'appearance_slot');
        });

        return back()->with('status','Frontend text restored to the original.');
    }

    public function typography(Request $request): RedirectResponse
    {
        $this->guard($request,true);
        $data=$request->validate([
            'family'=>['required',Rule::in(['Instrument Sans','Inter','Arial','Georgia','System UI','Custom'])],
            'base_size'=>['required','integer','between:14,20'],
            'heading_scale'=>['required','integer','between:90,120'],
            'weight'=>['required',Rule::in(['400','500','600','700','800','900',400,500,600,700,800,900])],
        ]);
        $previous=DB::table('studio_typography')->where('id',1)->first();
        if ($data['family']==='Custom' && !$previous?->custom_font_key) {
            return back()->withErrors(['family'=>'Upload a valid WOFF2 font before choosing Custom.']);
        }
        DB::transaction(function () use ($request,$data): void {
            DB::table('studio_typography')->updateOrInsert(['id'=>1],[
                'family'=>$data['family'],'base_size'=>(int)$data['base_size'],
                'heading_scale'=>(int)$data['heading_scale'],'weight'=>(int)$data['weight'],
                'updated_at'=>now(),
            ]);
            $this->audit($request,'appearance.typography.updated',1,$data);
        });
        return back()->with('status','Typography saved.');
    }

    public function uploadFont(Request $request): RedirectResponse
    {
        $this->guard($request,true);
        $request->validate(['font'=>['required','file','max:1024']]);
        $file=$request->file('font');
        abort_unless($file && strtolower($file->getClientOriginalExtension())==='woff2',422,
            'Only WOFF2 font files are accepted.');
        $bytes=$file->get();
        abort_unless(is_string($bytes) && strlen($bytes)>=64
            && substr($bytes,0,4)==='wOF2'
            && unpack('N',substr($bytes,8,4))[1]===strlen($bytes),422,'Invalid WOFF2 font.');
        $key='fonts/'.hash('sha256',$bytes).'.woff2';
        abort_unless(Storage::disk('local')->put($key,$bytes),503);
        $old=DB::table('studio_typography')->where('id',1)->first()?->custom_font_key;
        DB::transaction(function () use ($request,$key,$bytes): void {
            DB::table('studio_typography')->updateOrInsert(['id'=>1],[
                'custom_font_key'=>$key,'custom_font_sha256'=>hash('sha256',$bytes),
                'family'=>'Custom','updated_at'=>now(),
            ]);
            $this->audit($request,'appearance.font.uploaded',1,['sha256'=>hash('sha256',$bytes)]);
        });
        if ($old && $old!==$key) Storage::disk('local')->delete($old);
        return back()->with('status','Custom font uploaded and activated.');
    }

    public function font(): BinaryFileResponse
    {
        $record=StudioAppearance::typography();
        $key=$record->custom_font_key??'';
        abort_unless(preg_match('/\Afonts\/[a-f0-9]{64}\.woff2\z/D',$key),404);
        $disk=Storage::disk('local');
        $path=$disk->path($key);
        abort_unless(is_file($path) && !is_link($path)
            && hash_equals((string)$record->custom_font_sha256,(string)hash_file('sha256',$path)),404);
        return response()->file($path,[
            'Content-Type'=>'font/woff2','Cache-Control'=>'public, max-age=3600',
            'X-Content-Type-Options'=>'nosniff',
            'Access-Control-Allow-Origin'=>url('/'),
        ]);
    }

    public function showPublic(string $slug): View
    {
        $row=DB::table('studio_pages')->where('slug',$slug)
            ->where('is_custom',true)->where('is_published',true)->firstOrFail();
        return view('frontend.custom-page',['page'=>$row]);
    }
}
