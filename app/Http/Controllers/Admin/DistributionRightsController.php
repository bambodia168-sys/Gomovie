<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Support\StudioNavigation;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DistributionRightsController extends Controller
{
    private const STATUSES = ['pending' => 'Pending', 'active' => 'Active', 'revoked' => 'Revoked'];

    public function index(Request $request): View
    {
        abort_unless(StudioNavigation::administrator($request->user()), 403);
        $records = DB::table('content_rights as rights')
            ->leftJoin('movies', fn ($join) => $join->on('movies.id', 'rights.licensable_id')
                ->where('rights.licensable_type', 'movie'))
            ->leftJoin('episodes', fn ($join) => $join->on('episodes.id', 'rights.licensable_id')
                ->where('rights.licensable_type', 'episode'))
            ->whereNull('rights.territories')
            ->where(fn ($query) => $query->whereNotNull('movies.id')->orWhereNotNull('episodes.id'))
            ->select('rights.*', 'movies.title as movie_title', 'episodes.title as episode_title')
            ->orderByDesc('rights.updated_at')->orderByDesc('rights.id')->paginate(20);

        return view('admin.distribution-rights.index', ['records' => $records]);
    }

    public function edit(Request $request, string $kind, int $title): View
    {
        abort_unless(StudioNavigation::administrator($request->user()), 403);
        $model = $this->title($kind, $title);
        $query = $this->rights($kind, $title);
        $selected = $request->query('record');
        $record = null;
        if ($selected !== null) {
            abort_unless(is_scalar($selected) && ctype_digit((string) $selected), 404);
            $record = (clone $query)->where('id', (int) $selected)->first();
            abort_unless($record, 404);
        }

        return view('admin.distribution-rights.edit', [
            'kind' => $kind,
            'title' => $model,
            'records' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'record' => $record,
            'statuses' => self::STATUSES,
            'canEdit' => $request->user()->role === 'super_admin',
        ]);
    }

    public function save(Request $request, string $kind, int $title, ?int $record = null): RedirectResponse
    {
        abort_unless($request->user()->role === 'super_admin', 403);
        $data = $request->validate([
            'rights_reference' => ['required_if:status,active', 'nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', ...($request->filled('starts_at') ? ['after:starts_at'] : [])],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'rights_confirmed' => ['accepted_if:status,active'],
        ]);

        DB::transaction(function () use ($request, $kind, $title, $record, $data): void {
            $model = $kind === 'movie' ? Movie::class : Episode::class;
            $model::query()->lockForUpdate()->findOrFail($title);
            $now = now();
            $values = [
                'rights_reference' => $data['rights_reference'] ?? null,
                'starts_at' => isset($data['starts_at']) ? str_replace('T', ' ', $data['starts_at']).':00' : null,
                'ends_at' => isset($data['ends_at']) ? str_replace('T', ' ', $data['ends_at']).':00' : null,
                'status' => $data['status'],
                'updated_at' => $now,
            ];
            if ($record === null) {
                $record = DB::table('content_rights')->insertGetId([
                    'licensable_type' => $kind,
                    'licensable_id' => $title,
                    'created_at' => $now,
                    ...$values,
                ]);
            } else {
                $query = $this->rights($kind, $title)->where('id', $record);
                abort_unless($query->lockForUpdate()->first(), 404);
                $query->update($values);
            }

            DB::table('admin_audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'distribution_rights.saved',
                'resource_type' => 'content_rights',
                'resource_id' => $record,
                'details' => json_encode(['title_type' => $kind, 'title_id' => $title, 'status' => $data['status']], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return redirect()->route('admin.distribution-rights.edit', [$kind, $title])
            ->with('status', 'Distribution rights saved.');
    }

    private function title(string $kind, int $id): Movie|Episode
    {
        return $kind === 'movie'
            ? Movie::query()->findOrFail($id)
            : Episode::query()->with('season.series')->findOrFail($id);
    }

    private function rights(string $kind, int $title): Builder
    {
        return DB::table('content_rights')->where('licensable_type', $kind)
            ->where('licensable_id', $title)->whereNull('territories');
    }
}
