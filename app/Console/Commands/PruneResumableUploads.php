<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Expired resumable staging is removed independently of real media ingests.
 * Run via configured Laravel scheduler; no public route or deletion of videos.
 */
final class PruneResumableUploads extends Command
{
    protected $signature = 'gomovie:prune-resumable-uploads {--limit=50 : Maximum expired sessions to inspect}';
    protected $description = 'Safely prune expired, private resumable upload chunks.';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500) {
            $this->error('Limit must be between 1 and 500.');
            return self::FAILURE;
        }

        $rows = DB::table('resumable_upload_sessions')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')->limit($limit)->get(['id','status']);
        $removed = 0;
        foreach ($rows as $candidate) {
            if (!Str::isUuid($candidate->id)) continue;
            $claimed = DB::transaction(function () use ($candidate): bool {
                $row = DB::table('resumable_upload_sessions')
                    ->where('id', $candidate->id)->lockForUpdate()->first();
                if (!$row || Carbon::parse($row->expires_at)->isFuture()
                    || $row->status === 'pruning') return false;
                // No database or source-media deletes here.
                DB::table('resumable_upload_sessions')->where('id', $row->id)
                    ->update(['status' => 'pruning','updated_at' => now()]);
                return true;
            });
            if (!$claimed) continue;
            // The staging prefix contains only private chunk fragments.
            $ok = Storage::disk('local')->deleteDirectory('resumable/'.$candidate->id);
            if (!$ok) {
                $this->warn('Private fragment cleanup could not finish: '.$candidate->id);
                continue; // Retry on a later scheduler run.
            }
            DB::transaction(function () use ($candidate): void {
                DB::table('resumable_upload_sessions')
                    ->where('id', $candidate->id)->where('status', 'pruning')->delete();
            });
            $removed++;
        }
        $this->info('Expired private upload sessions pruned: '.$removed);
        return self::SUCCESS;
    }
}
