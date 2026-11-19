<?php

namespace App\Services\Media;

use App\Jobs\AutoPublishUploadedVideo;
use App\Support\SiteSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class PublicationRecovery
{
    // Allow the worker's 7,500-second timeout and reservation window to elapse.
    public const STALE_SECONDS = 7800;

    public const STAGES = [
        'quarantined' => 'Waiting for scan',
        'approved' => 'Waiting for encoder',
        'processing' => 'Encoding',
        'encoded' => 'Encoded',
        'failed' => 'Processing failed',
        'rejected' => 'Rejected',
    ];

    public function stageStartedAt(object $ingest): ?Carbon
    {
        $stamp = match ($ingest->status) {
            'quarantined' => $ingest->created_at,
            'approved' => $ingest->approved_at ?? $ingest->updated_at,
            'processing' => $ingest->processing_started_at ?? $ingest->updated_at,
            'encoded' => $ingest->encoded_at ?? $ingest->updated_at,
            default => $ingest->updated_at,
        };

        return $stamp ? Carbon::parse($stamp) : null;
    }

    public function retryPending(object $ingest): bool
    {
        return $ingest->publication_retry_token && $ingest->publication_retry_queued_at
            && Carbon::parse($ingest->publication_retry_queued_at)->isAfter(now()->subSeconds(self::STALE_SECONDS));
    }

    public function stalled(object $ingest): bool
    {
        return in_array($ingest->status, ['quarantined', 'approved', 'processing', 'encoded'], true)
            && $this->stageStartedAt($ingest)?->lte(now()->subSeconds(self::STALE_SECONDS));
    }

    private function publishedPackages(): Builder
    {
        return DB::table('media_assets')
            ->where('storage_disk', 'local')->where('format', 'hls')->where('status', 'ready')
            ->whereExists(fn ($files) => $files->selectRaw('1')->from('public_media_files')
                ->whereColumn('media_asset_id', 'media_assets.id'));
    }

    private function hasPublishedPackage(object $ingest): bool
    {
        return $ingest->status === 'encoded' && $ingest->encoded_manifest_key
            && $this->publishedPackages()->where('streamable_type', $ingest->ingestable_type)
                ->where('streamable_id', $ingest->ingestable_id)
                ->where('manifest_key', $ingest->encoded_manifest_key)->exists();
    }

    public function issues(): Builder
    {
        $old = now()->subSeconds(self::STALE_SECONDS);
        $published = $this->publishedPackages()->selectRaw('1')
            ->whereColumn('streamable_type', 'media_ingests.ingestable_type')
            ->whereColumn('streamable_id', 'media_ingests.ingestable_id')
            ->whereColumn('manifest_key', 'media_ingests.encoded_manifest_key');

        return DB::table('media_ingests')->where(function ($query) use ($old): void {
            $query->whereNotNull('pipeline_error')->orWhereIn('status', ['failed', 'rejected'])
                ->orWhere(function ($stalled) use ($old): void {
                    $stalled->where('auto_publication_requested', true)->where(function ($stages) use ($old): void {
                        $stages->where(fn ($stage) => $stage->where('status', 'quarantined')->where('created_at', '<=', $old))
                            ->orWhere(fn ($stage) => $stage->where('status', 'approved')->whereRaw('COALESCE(approved_at, updated_at) <= ?', [$old]))
                            ->orWhere(fn ($stage) => $stage->where('status', 'processing')->whereRaw('COALESCE(processing_started_at, updated_at) <= ?', [$old]))
                            ->orWhere(fn ($stage) => $stage->where('status', 'encoded')->whereRaw('COALESCE(encoded_at, updated_at) <= ?', [$old]));
                    });
                });
        })->where(fn ($query) => $query->where('status', '!=', 'encoded')->orWhereNotExists($published));
    }

    private function retryableState(object $ingest): bool
    {
        return $ingest->auto_publication_requested && $ingest->disk === 'local'
            && in_array($ingest->status, ['quarantined', 'approved', 'processing', 'failed', 'encoded'], true)
            && $ingest->security_scan_status !== 'infected' && $ingest->rights_review_status !== 'rejected';
    }

    public function canRetry(object $ingest): bool
    {
        return $this->retryableState($ingest) && !$this->retryPending($ingest)
            && ($ingest->pipeline_error || $ingest->status === 'failed' || $this->stalled($ingest))
            && !$this->hasPublishedPackage($ingest);
    }

    public function queue(int $ingestId, int $actorId): bool
    {
        $pipeline = app(AutomaticPublicationService::class);
        abort_unless($pipeline->enabled() && SiteSettings::get('transcoding_enabled') === true, 409,
            'Enable automatic publication, HLS playback and FFmpeg in Site Settings before retrying.');

        $token = DB::transaction(function () use ($ingestId, $actorId, $pipeline): ?string {
            $ingest = DB::table('media_ingests')->where('id', $ingestId)->lockForUpdate()->first();
            abort_unless($ingest, 404);
            if ($this->retryPending($ingest)) {
                return null;
            }
            abort_unless($this->canRetry($ingest), 409, 'This upload is not eligible for a processing retry.');
            $pipeline->requireRetryEligibility($ingest);

            $token = (string) Str::uuid();
            DB::table('media_ingests')->where('id', $ingestId)->update([
                'publication_retry_token' => $token,
                'publication_retry_queued_at' => now(),
            ]);
            DB::table('admin_audit_events')->insert([
                'actor_id' => $actorId, 'action' => 'media.publication_retry_requested',
                'resource_type' => 'media_ingest', 'resource_id' => $ingestId,
                'details' => json_encode(['status' => $ingest->status], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $token;
        });

        if ($token === null) {
            return false;
        }
        try {
            Bus::dispatch(new AutoPublishUploadedVideo($ingestId, $token));
        } catch (Throwable $error) {
            $this->clearRetry($ingestId, $token);
            report($error);
            abort(503, 'Could not queue this retry. Check the media worker in System Health.');
        }

        return true;
    }

    public function resume(int $ingestId, string $token, AutomaticPublicationService $pipeline): void
    {
        $ingest = DB::transaction(function () use ($ingestId, $token): ?object {
            $query = DB::table('media_ingests')->where('id', $ingestId)->where('publication_retry_token', $token);
            $ingest = $query->lockForUpdate()->first();
            if ($ingest) {
                $query->update(['publication_retry_queued_at' => now()]);
            }
            return $ingest;
        });
        if (!$ingest) {
            return;
        }

        try {
            abort_unless($pipeline->enabled() && SiteSettings::get('transcoding_enabled') === true, 409,
                'Automatic media processing has been disabled.');
            abort_unless($this->retryableState($ingest), 409, 'This upload is no longer eligible for retry.');
            $pipeline->requireRetryEligibility($ingest);

            if ($this->hasPublishedPackage($ingest)) {
                return;
            }
            if ($ingest->status === 'encoded') {
                $pipeline->finalize($ingestId);
            } else {
                // Start again from the unchanged private source, including a fresh scan.
                $changed = DB::table('media_ingests')->where('id', $ingestId)
                    ->where('publication_retry_token', $token)->where('status', $ingest->status)
                    ->where('security_scan_status', '!=', 'infected')->update([
                        'status' => 'quarantined', 'security_scan_status' => 'pending',
                        'rights_review_status' => 'pending', 'approved_by' => null,
                        'approved_at' => null, 'processing_started_at' => null,
                        'updated_at' => now(),
                    ]);
                abort_unless($changed === 1, 409, 'The upload changed during recovery.');
                $pipeline->process($ingestId);
            }
            DB::table('media_ingests')->where('id', $ingestId)
                ->where('publication_retry_token', $token)->where('status', 'encoded')
                ->update(['pipeline_error' => null]);
        } finally {
            $this->clearRetry($ingestId, $token);
        }
    }

    private function clearRetry(int $ingestId, string $token): void
    {
        DB::table('media_ingests')->where('id', $ingestId)->where('publication_retry_token', $token)
            ->update(['publication_retry_token' => null, 'publication_retry_queued_at' => null]);
    }
}
