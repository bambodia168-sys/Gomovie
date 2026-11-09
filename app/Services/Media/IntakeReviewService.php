<?php

namespace App\Services\Media;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * A compact operator decision, not an automatic legal-rights determination.
 * Requires real malware scanning and an operator-supplied evidence reference.
 * Final HLS review/publication remains a separate independent gate.
 */
final class IntakeReviewService
{
    public function approve(int $id, User $operator, string $rightsReference, ?string $ip): void
    {
        $this->requireAdministrator($operator);
        abort_unless(preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9:._\/-]{7,179}\z/D', $rightsReference),
            422, 'Provide a valid documented rights reference (8–180 characters).');

        $ingest = DB::table('media_ingests')->where('id', $id)->first();
        abort_unless($ingest && $ingest->status === 'quarantined', 409,
            'Only quarantined uploads can be approved.');
        abort_unless(in_array($ingest->ingestable_type, ['movie', 'episode'], true), 422);
        $target = DB::table($ingest->ingestable_type === 'movie' ? 'movies' : 'episodes')
            ->where('id', $ingest->ingestable_id)->first();
        abort_unless($target, 404);

        $binary = (string) config('gomovie.malware_scanner_binary');
        abort_unless(str_starts_with($binary, '/')
            && basename($binary) === 'clamscan'
            && is_file($binary) && is_executable($binary), 503,
            'Malware scanner is not installed or configured. Upload remains safely quarantined.');

        $path = (string) $ingest->object_key;
        abort_unless($ingest->disk === 'local' &&
            preg_match('#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.(mp4|mov|mkv|webm)\z#iD', $path),
            422, 'Unsafe original video path.');
        $localPath = Storage::disk('local')->path($path);
        $root = realpath(storage_path('app/private'));
        $real = realpath($localPath);
        abort_unless($root && $real && !is_link($localPath)
            && str_starts_with($real, $root.DIRECTORY_SEPARATOR)
            && is_file($real) && filesize($real) === (int) $ingest->size_bytes
            && hash_equals((string) $ingest->sha256, (string) hash_file('sha256', $real)),
            422, 'Private uploaded video failed integrity validation.');

        // Scan failures must be a controlled fail-closed result, not HTTP 500.
        // Never approve or license content when the scanner cannot finish.
        try {
            $scan = new Process([$binary, '--no-summary', '--infected', $real]);
            $scan->setTimeout(300);
            $scan->run();
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'The security scanner could not complete. The upload remains private.');
        }
        $exit = $scan->getExitCode();
        if ($exit === 1) {
            DB::transaction(function () use ($id, $operator, $ip): void {
                DB::table('media_ingests')->where('id', $id)->where('status', 'quarantined')
                    ->update(['status' => 'rejected', 'security_scan_status' => 'infected', 'updated_at' => now()]);
                $this->audit($operator->id, 'media.intake_malware_rejected', $id, $ip);
            });
            abort(422, 'Potential malware detected. The upload was rejected.');
        }
        abort_unless($exit === 0, 503, 'Malware scan failed. Upload remains quarantined.');

        // Re-check the source after the scanner reads it. The approval is
        // operator-attested; no application can independently verify contracts.
        abort_unless(hash_equals((string) $ingest->sha256, (string) hash_file('sha256', $real)),
            422, 'Video changed during malware scanning.');

        DB::transaction(function () use ($id, $operator, $rightsReference, $ingest, $ip): void {
            $locked = DB::table('media_ingests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($locked && $locked->status === 'quarantined'
                && hash_equals((string) $ingest->sha256, (string) $locked->sha256), 409,
                'Upload changed during review.');

            $rights = DB::table('content_rights')
                ->where('licensable_type', $locked->ingestable_type)
                ->where('licensable_id', $locked->ingestable_id)
                ->where('status', 'active')->whereNull('territories')
                ->where('rights_reference', $rightsReference)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->exists();

            if (!$rights) {
                DB::table('content_rights')->insert([
                    'licensable_type' => $locked->ingestable_type,
                    'licensable_id' => $locked->ingestable_id,
                    'rights_reference' => $rightsReference,
                    'territories' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('media_ingests')->where('id', $id)->update([
                'status' => 'approved', 'security_scan_status' => 'clean',
                'rights_review_status' => 'verified',
                'approved_by' => $operator->id, 'approved_at' => now(),
                'review_notes' => 'Operator-attested rights evidence: '.$rightsReference,
                'updated_at' => now(),
            ]);
            $this->audit($operator->id, 'media.intake_approved', $id, $ip, [
                'rights_reference' => $rightsReference,
                'rights_basis' => 'operator_attestation',
                'malware_scanner' => 'clamscan',
            ]);
        });
    }

    public function reject(int $id, User $operator, string $reason, ?string $ip): void
    {
        $this->requireAdministrator($operator);
        abort_unless(mb_strlen(trim($reason)) >= 8 && mb_strlen($reason) <= 1000, 422,
            'Provide a short reason for rejecting the upload.');
        DB::transaction(function () use ($id, $operator, $reason, $ip): void {
            $ingest = DB::table('media_ingests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ingest && $ingest->status === 'quarantined', 409,
                'Only quarantined uploads can be rejected.');
            DB::table('media_ingests')->where('id', $id)->update([
                'status' => 'rejected', 'review_notes' => trim($reason), 'updated_at' => now(),
            ]);
            $this->audit($operator->id, 'media.intake_rejected', $id, $ip, [
                'reason' => trim($reason),
            ]);
        });
    }

    private function requireAdministrator(User $user): void
    {
        abort_unless($user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, ['admin', 'super_admin'], true), 403);
    }

    private function audit(int $operator, string $action, int $id, ?string $ip, array $details = []): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id' => $operator, 'action' => $action,
            'resource_type' => 'media_ingest', 'resource_id' => $id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'ip_address' => $ip, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
