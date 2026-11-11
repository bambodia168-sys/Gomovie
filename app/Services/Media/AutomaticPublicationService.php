<?php

namespace App\Services\Media;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Series;
use App\Services\Rights\GlobalRights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Optional, single-upload auto-publication for pre-licensed FREE catalog titles.
 * No media is publicly served until integrity, malware and HLS checks pass.
 * The source stays private; the HLS delivery controller validates each request.
 */
final class AutomaticPublicationService
{
    public function enabled(): bool
    {
        return config('gomovie.automatic_publication_enabled') === true && $this->publicEnabled();
    }

    public function publicEnabled(): bool
    {
        return config('gomovie.public_hls_enabled') === true;
    }

    public function readyAsset(string $kind, int $titleId): ?object
    {
        if (!$this->publicEnabled() || !in_array($kind, ['movie', 'episode'], true) || $titleId < 1) {
            return null;
        }
        if (!app(GlobalRights::class)->allows($kind, $titleId)
            || !$this->hasDocumentedRights($kind, $titleId)) return null;
        $target = $this->eligibleTitle($kind, $titleId, true);
        if (!$target) return null;
        return DB::table('media_assets as assets')
            ->join('media_ingests as ingests', 'ingests.encoded_manifest_key', '=', 'assets.manifest_key')
            ->where('assets.streamable_type', $kind)
            ->where('assets.streamable_id', $titleId)
            ->where('assets.storage_disk', 'local')
            ->where('assets.format', 'hls')
            ->where('assets.status', 'ready')
            ->whereNull('assets.drm_scheme')
            ->where('ingests.ingestable_type', $kind)
            ->where('ingests.ingestable_id', $titleId)
            ->where('ingests.status', 'encoded')
            ->where('ingests.security_scan_status', 'clean')
            ->where('ingests.rights_review_status', 'verified')
            ->whereNotNull('ingests.approved_at')
            ->whereNotNull('ingests.approved_by')
            ->whereColumn('ingests.approved_by', '!=', 'ingests.uploaded_by')
            ->join('media_publication_reviews as reviews', function ($join): void {
                $join->on('reviews.id', '=', 'assets.media_publication_review_id')
                    ->on('reviews.media_ingest_id', '=', 'ingests.id');
            })
            ->where('reviews.status', 'reviewed')
            ->whereColumn('reviews.reviewed_by', '!=', 'ingests.uploaded_by')
            ->whereColumn('reviews.reviewed_by', '!=', 'ingests.approved_by')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('content_rights as documented')
                ->whereColumn('documented.licensable_type', 'ingests.ingestable_type')
                ->whereColumn('documented.licensable_id', 'ingests.ingestable_id')
                ->whereColumn('documented.rights_reference', 'reviews.rights_reference')
                ->where('documented.status', 'active')
                ->whereNotNull('documented.rights_reference')
                ->where('documented.rights_reference', 'not like', 'OWNER-DECLARED-%')
                ->whereNull('documented.territories')
                ->where(fn ($q) => $q->whereNull('documented.starts_at')
                    ->orWhere('documented.starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('documented.ends_at')
                    ->orWhere('documented.ends_at', '>', now())))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('public_media_files as files')
                ->whereColumn('files.media_asset_id', 'assets.id'))
            ->select('assets.id', 'assets.manifest_key')
            ->orderByDesc('assets.id')->first();
    }

    /** Preexisting verified distribution rights, never fabricated by upload. */
    private function eligibleTitle(string $kind, int $id, bool $published): Movie|Episode|null
    {
        if ($kind === 'movie') {
            $movie = Movie::query()->find($id);
            if (!$movie || $movie->is_premium || $movie->status === 'archived'
                || ($published && ($movie->status !== 'published'
                    || ($movie->published_at && $movie->published_at->isFuture())))) return null;
            return $movie;
        }
        if ($kind !== 'episode') return null;
        $episode = Episode::query()->with('season.series')->find($id);
        $series = $episode?->season?->series;
        if (!$episode || !$series || $series->is_premium
            || $episode->status === 'archived' || $series->status === 'archived'
            || ($published && ($episode->status !== 'published' || $series->status !== 'published'
                || ($episode->published_at && $episode->published_at->isFuture())
                || ($series->published_at && $series->published_at->isFuture())))) return null;
        return $episode;
    }

    public function process(int $ingestId): void
    {
        if (!$this->enabled() || config('gomovie.transcoding_enabled') !== true) return;
        $ingest = DB::table('media_ingests')->where('id', $ingestId)->first();
        if (!$ingest || $ingest->status !== 'quarantined') return;

        // ISSUE #10: automated malware scanning does not establish copyright,
        // independent operator approval or an immutable package review.
        // Never promote this ingest or run public encoding from this worker.
        DB::table('media_ingests')->where('id', $ingestId)
            ->where('status', 'quarantined')
            ->update([
                'pipeline_error' => 'independent_rights_and_publication_review_required',
                'updated_at' => now(),
            ]);
        return;
        $kind = (string) $ingest->ingestable_type;
        $titleId = (int) $ingest->ingestable_id;
        if (!$this->eligibleTitle($kind, $titleId, false)
            || !app(GlobalRights::class)->allows($kind, $titleId)) {
            DB::table('media_ingests')->where('id', $ingestId)->where('status', 'quarantined')
                ->update(['pipeline_error' => 'active_free_distribution_rights_required', 'updated_at' => now()]);
            return;
        }
        $rights = $this->hasDocumentedRights($kind, $titleId);
        if (!$rights) {
            DB::table('media_ingests')->where('id', $ingestId)->where('status', 'quarantined')
                ->update(['pipeline_error' => 'documented_distribution_rights_required', 'updated_at' => now()]);
            return;
        }

        $uploader = DB::table('users')->where('id', $ingest->uploaded_by)->first();
        abort_unless($uploader && $uploader->is_active && $uploader->email_verified_at
            && in_array($uploader->role, ['admin', 'super_admin'], true), 403);

        $binary = (string) config('gomovie.malware_scanner_binary');
        abort_unless(str_starts_with($binary, '/') && basename($binary) === 'clamscan'
            && is_file($binary) && is_executable($binary), 503, 'Trusted malware scanner unavailable.');
        $key = (string) $ingest->object_key;
        abort_unless($ingest->disk === 'local' && preg_match(
            '#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.(mp4|mov|mkv|webm)\z#iD', $key
        ), 422, 'Invalid uploaded file path.');
        $disk = Storage::disk('local');
        $location = $disk->path($key);
        $root = realpath($disk->path(''));
        $real = realpath($location);
        abort_unless($root && $real && !is_link($location)
            && str_starts_with($real, $root.DIRECTORY_SEPARATOR)
            && is_file($real) && filesize($real) === (int) $ingest->size_bytes
            && hash_equals((string) $ingest->sha256, (string) hash_file('sha256', $real)),
            422, 'Private video integrity failed.');

        try {
            $scan = new Process([$binary, '--no-summary', '--infected', $real]);
            $scan->setTimeout(300);
            $scan->run();
        } catch (Throwable $error) {
            report($error);
            abort(503, 'Malware scanning unavailable.');
        }
        if ($scan->getExitCode() === 1) {
            DB::table('media_ingests')->where('id', $ingestId)->where('status', 'quarantined')
                ->update(['status' => 'rejected', 'security_scan_status' => 'infected',
                    'pipeline_error' => 'malware_detected', 'updated_at' => now()]);
            return;
        }
        abort_unless($scan->getExitCode() === 0
            && hash_equals((string) $ingest->sha256, (string) hash_file('sha256', $real)),
            503, 'Malware scanning failed or file changed.');

        $claimed = DB::transaction(function () use ($ingestId, $ingest, $kind, $titleId): bool {
            $locked = DB::table('media_ingests')->where('id', $ingestId)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'quarantined'
                || !hash_equals((string) $locked->sha256, (string) $ingest->sha256)
                || !$this->eligibleTitle($kind, $titleId, false)
                || !app(GlobalRights::class)->allows($kind, $titleId)) return false;
            DB::table('media_ingests')->where('id', $ingestId)->update([
                'status' => 'approved', 'security_scan_status' => 'clean',
                'rights_review_status' => 'verified', 'approved_by' => $ingest->uploaded_by,
                'approved_at' => now(), 'pipeline_error' => null, 'updated_at' => now(),
            ]);
            $this->audit((int) $ingest->uploaded_by, $ingestId, 'media.auto_scan_passed');
            return true;
        });
        if (!$claimed) return;

        app(TranscodingService::class)->encode($ingestId);
        $this->finalize($ingestId);
    }

    /**
     * Called only after verified encoding; rechecks full HLS files and rights.
     * Exposed as a service for isolated, synthetic acceptance testing.
     */
    public function finalize(int $ingestId): ?int
    {
        if (!$this->enabled()) return null;
        $ingest = DB::table('media_ingests')->where('id', $ingestId)->first();
        abort_unless($ingest && $ingest->status === 'encoded'
            && $ingest->security_scan_status === 'clean'
            && $ingest->rights_review_status === 'verified'
            && $ingest->approved_by && $ingest->approved_at
            && (int) $ingest->approved_by !== (int) $ingest->uploaded_by, 403,
            'Scanned, approved encoding is required.');
        $kind = (string) $ingest->ingestable_type;
        $id = (int) $ingest->ingestable_id;
        abort_unless($this->eligibleTitle($kind, $id, false), 403, 'Free eligible catalog title required.');
        app(GlobalRights::class)->requireActive($kind, $id);
        abort_unless($this->hasDocumentedRights($kind, $id), 403,
            'A documented active global distribution-rights record is required.');
        // The package inspector enforces safe manifests, relative paths, bytes and checksums.
        $package = app(MediaPackageInspector::class)->inspect($ingestId, true);
        $review = DB::table('media_publication_reviews')
            ->where('media_ingest_id', $ingestId)
            ->where('status', 'reviewed')->first();
        abort_unless($review
            && (int) $review->reviewed_by !== (int) $ingest->uploaded_by
            && (int) $review->reviewed_by !== (int) $ingest->approved_by
            && hash_equals((string) $review->manifest_sha256, $package['manifest_sha256'])
            && hash_equals((string) $review->package_sha256, $package['package_sha256'])
            && DB::table('content_rights')->where('licensable_type', $kind)
                ->where('licensable_id', $id)->where('status', 'active')
                ->where('rights_reference', $review->rights_reference)
                ->where('rights_reference', 'not like', 'OWNER-DECLARED-%')
                ->whereNull('territories')->exists(), 403,
            'Independent current media package and rights review is required.');

        return DB::transaction(function () use ($ingest, $kind, $id, $package, $ingestId, $review): int {
            $locked = DB::table('media_ingests')->where('id', $ingestId)->lockForUpdate()->first();
            abort_unless($locked && $locked->status === 'encoded'
                && $locked->encoded_manifest_key === $ingest->encoded_manifest_key
                && $locked->sha256 === $ingest->sha256, 409, 'Ingest changed during publication.');
            $title = $this->eligibleTitle($kind, $id, false);
            abort_unless($title && app(GlobalRights::class)->allows($kind, $id)
                && $this->hasDocumentedRights($kind, $id), 403,
                'Rights or catalog status changed during publication.');
            if ($kind === 'episode') {
                $series = Series::query()->findOrFail($title->season->series->id);
                abort_unless(!$series->is_premium && $series->status !== 'archived', 403);
                if ($series->status !== 'published') {
                    $series->forceFill(['status' => 'published', 'published_at' => now()])->save();
                }
            }
            if ($title->status !== 'published') {
                $title->forceFill(['status' => 'published', 'published_at' => now()])->save();
            }
            $freshReview = DB::table('media_publication_reviews')
                ->where('id', $review->id)->where('media_ingest_id', $ingestId)
                ->where('status', 'reviewed')->lockForUpdate()->first();
            abort_unless($freshReview && $freshReview->rights_reference === $review->rights_reference
                && hash_equals((string) $freshReview->manifest_sha256, $package['manifest_sha256'])
                && hash_equals((string) $freshReview->package_sha256, $package['package_sha256'])
                && (int) $freshReview->reviewed_by !== (int) $locked->uploaded_by
                && (int) $freshReview->reviewed_by !== (int) $locked->approved_by, 403,
                'Reviewed package changed during publication.');
            $existing = DB::table('media_assets')->where('streamable_type', $kind)
                ->where('streamable_id', $id)->where('manifest_key', $ingest->encoded_manifest_key)
                ->first();
            if ($existing) {
                abort_unless((int) $existing->media_publication_review_id === (int) $review->id,
                    409, 'Existing media asset lacks the accepted independent review.');
                return (int) $existing->id;
            }
            $assetId = (int) DB::table('media_assets')->insertGetId([
                'streamable_type' => $kind, 'streamable_id' => $id,
                'storage_disk' => 'local', 'manifest_key' => $ingest->encoded_manifest_key,
                'format' => 'hls', 'status' => 'ready', 'drm_scheme' => null,
                'media_publication_review_id' => $review->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($package['files'] as $name => $file) {
                DB::table('public_media_files')->insert([
                    'media_asset_id' => $assetId, 'relative_path' => $name,
                    'sha256' => $file['sha256'], 'size_bytes' => $file['bytes'],
                ]);
            }
            $this->audit((int) $ingest->uploaded_by, $ingestId, 'media.auto_publication_ready',
                ['media_asset_id' => $assetId, 'package_sha256' => $package['package_sha256']]);
            return $assetId;
        });
    }

    private function hasDocumentedRights(string $kind, int $id): bool
    {
        return DB::table('content_rights')->where('licensable_type', $kind)
            ->where('licensable_id', $id)->where('status', 'active')
            ->whereNull('territories')->whereNotNull('rights_reference')
            ->where('rights_reference', '!=', '')
            ->where('rights_reference', 'not like', 'OWNER-DECLARED-%')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();
    }

    public function failure(int $ingestId, Throwable $error): void
    {
        report($error);
        DB::table('media_ingests')->where('id', $ingestId)
            ->whereIn('status', ['quarantined', 'approved', 'processing', 'encoded'])
            ->update(['pipeline_error' => 'automatic_publication_failed', 'updated_at' => now()]);
    }

    private function audit(int $actor, int $ingest, string $action, array $fields = []): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id' => $actor, 'action' => $action,
            'resource_type' => 'media_ingest', 'resource_id' => $ingest,
            'details' => json_encode($fields, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
