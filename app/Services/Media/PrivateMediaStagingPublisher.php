<?php

namespace App\Services\Media;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Copies an independently reviewed HLS package to a PRIVATE S3/R2 bucket.
 * Never creates a media_assets row or activates playback, even on success.
 */
final class PrivateMediaStagingPublisher
{
    public function stage(int $ingestId, User $publisher): int
    {
        abort_unless(config('gomovie.media_staging_enabled') === true, 503,
            'Private media staging is disabled.');
        abort_unless($publisher->is_active && $publisher->hasVerifiedEmail()
            && in_array($publisher->role, ['admin','super_admin'], true), 403,
            'A verified administrator is required.');

        $package = app(MediaPackageInspector::class)->inspect($ingestId);
        $review = DB::table('media_publication_reviews')
            ->where('media_ingest_id', $ingestId)->where('status', 'reviewed')->first();
        abort_unless($review
            && hash_equals((string) $review->manifest_sha256, $package['manifest_sha256']),
            403, 'Missing or outdated independent media package review.');
        abort_if((int) $publisher->id === (int) $review->reviewed_by, 403,
            'Staging operator must differ from package reviewer.');

        $ingest = $package['ingest'];
        $matching = DB::table('content_rights')
            ->where('licensable_type', $ingest->ingestable_type)
            ->where('licensable_id', $ingest->ingestable_id)
            ->where('rights_reference', $review->rights_reference)
            ->where('status', 'active')->whereNull('territories')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists();
        abort_unless($matching, 403, 'Reviewed distribution rights are no longer active.');

        $prefix = 'gomovie/unreleased/'.$ingest->ingestable_type.'/'
            .$ingest->ingestable_id.'/'.$ingestId.'/'.substr($package['manifest_sha256'], 0, 16);
        $manifestKey = $prefix.'/master.m3u8';
        $batchId = DB::transaction(function () use ($ingestId, $review, $publisher, $prefix, $manifestKey, $package): int {
            abort_if(DB::table('media_publication_batches')->where('media_ingest_id', $ingestId)
                ->lockForUpdate()->exists(), 409, 'A stage attempt already exists for this ingest.');
            return DB::table('media_publication_batches')->insertGetId([
                'media_ingest_id'=>$ingestId,
                'media_publication_review_id'=>$review->id,
                'staged_by'=>$publisher->id, 'status'=>'staging',
                'object_prefix'=>$prefix, 'manifest_key'=>$manifestKey,
                'manifest_sha256'=>$package['manifest_sha256'],
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
        });

        $remote = Storage::disk('s3');
        $local = Storage::disk('local');
        $uploaded = [];
        try {
            // No public ACL, no externally resolvable URL, and no ready asset.
            foreach ($package['files'] as $relative => $metadata) {
                $localPath = $local->path($metadata['key']);
                abort_unless(!is_link($localPath) && is_file($localPath)
                    && hash_equals($metadata['sha256'], (string) hash_file('sha256', $localPath)),
                    422, 'Private media changed after review.');
                $key = $prefix.'/'.$relative;
                if ($remote->exists($key)) {
                    throw new RuntimeException('Unexpected existing private staging object.');
                }
                $stream = fopen($localPath, 'rb');
                if (!is_resource($stream)) {
                    throw new RuntimeException('Unable to stream private HLS data.');
                }
                try {
                    if (!$remote->put($key, $stream, ['visibility'=>'private'])) {
                        throw new RuntimeException('Private media staging upload failed.');
                    }
                } finally {
                    fclose($stream);
                }
                $uploaded[] = $key;
                if (!$remote->exists($key)
                    || $remote->size($key) !== $metadata['bytes']
                    || $remote->visibility($key) !== 'private') {
                    throw new RuntimeException('Private staging object verification failed.');
                }
            }

            DB::transaction(function () use ($batchId, $ingestId, $publisher, $package): void {
                DB::table('media_publication_batches')->where('id', $batchId)
                    ->where('status', 'staging')->update([
                        'status'=>'staged', 'file_count'=>$package['file_count'],
                        'staged_at'=>now(), 'updated_at'=>now(),
                    ]);
                DB::table('admin_audit_events')->insert([
                    'actor_id'=>$publisher->id, 'action'=>'media.private_package_staged',
                    'resource_type'=>'media_ingest', 'resource_id'=>$ingestId,
                    'details'=>json_encode(['batch_id'=>$batchId,
                        'manifest_sha256'=>$package['manifest_sha256'],
                        'file_count'=>$package['file_count']], JSON_THROW_ON_ERROR),
                    'created_at'=>now(), 'updated_at'=>now(),
                ]);
            });
            return $batchId;
        } catch (Throwable $error) {
            try {
                if ($uploaded !== []) $remote->delete($uploaded);
            } catch (Throwable $cleanupError) {
                report($cleanupError);
            }
            DB::table('media_publication_batches')->where('id', $batchId)
                ->where('status','staging')->update([
                    'status'=>'failed', 'failure_code'=>'private_staging_failed',
                    'updated_at'=>now(),
                ]);
            report($error);
            throw new RuntimeException('Private media staging failed; inspect protected logs and remote object cleanup.', 0, $error);
        }
    }
}
