<?php

namespace App\Services\Rights;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Admin-submitted private legal evidence; independent human verification is
 * recorded, not performed automatically by the software. Never a public file.
 */
final class RightsEvidenceService
{
    private function administrator(User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasVerifiedEmail()
            && in_array($actor->role, ['admin', 'super_admin'], true), 403);
    }

    public function submit(User $actor, array $data, UploadedFile $file, ?string $ip): int
    {
        $this->administrator($actor);
        $reference = trim((string) $data['rights_reference']);
        abort_unless(preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._\/-]{7,179}\z/D', $reference)
            && !str_starts_with(strtoupper($reference), 'OWNER-DECLARED-'), 422,
            'Only a separately documented reference is allowed.');
        abort_unless($data['ends_at'] > $data['starts_at'], 422);
        $kind = $data['licensable_type'];
        $table = $kind === 'movie' ? 'movies' : 'episodes';
        abort_unless(in_array($kind, ['movie', 'episode'], true)
            && DB::table($table)->where('id', $data['licensable_id'])->exists(), 404);

        abort_unless($file->isValid() && $file->getSize() > 0
            && $file->getSize() <= 8388608, 422);
        $path = $file->getRealPath();
        abort_unless($path && is_file($path) && !is_link($path), 422);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };
        abort_unless($extension, 422, 'Only PDF, JPEG and PNG license evidence is accepted.');
        $sha = hash_file('sha256', $path);
        $size = filesize($path);
        abort_unless(is_string($sha) && $size > 0, 422);

        // Real scanner is mandatory. CI may use a clearly labeled synthetic
        // fixture, but that cannot certify real hosting malware protection.
        $binary = (string) config('gomovie.malware_scanner_binary');
        abort_unless(str_starts_with($binary, '/')
            && basename($binary) === 'clamscan'
            && is_file($binary) && is_executable($binary), 503,
            'Trusted ClamAV scanner is unavailable. Evidence stays unapproved.');
        try {
            $scan = new Process([$binary, '--no-summary', '--infected', $path]);
            $scan->setTimeout(120);
            $scan->run();
        } catch (Throwable $error) {
            report($error);
            abort(503, 'Document malware scan failed. No license was created.');
        }
        abort_unless($scan->getExitCode() === 0
            && hash_equals($sha, (string) hash_file('sha256', $path))
            && filesize($path) === $size, 422,
            'Document failed malware or integrity verification.');

        $key = 'rights-evidence/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('local');
        $stream = fopen($path, 'rb');
        abort_unless($stream !== false, 503);
        try {
            abort_unless($disk->writeStream($key, $stream, ['visibility' => 'private']),
                503, 'Private document storage failed.');
        } finally {
            fclose($stream);
        }
        try {
            $stored = $disk->path($key);
            abort_unless(is_file($stored) && !is_link($stored)
                && filesize($stored) === $size
                && hash_equals($sha, (string) hash_file('sha256', $stored)),
                503, 'Stored document integrity failed.');
            return DB::transaction(function () use ($actor, $data, $reference, $key, $sha, $size, $mime, $ip): int {
                $id = DB::table('rights_evidence')->insertGetId([
                    'licensable_type' => $data['licensable_type'],
                    'licensable_id' => $data['licensable_id'],
                    'rights_reference' => $reference,
                    'rights_holder' => trim($data['rights_holder']),
                    'license_scope' => 'global',
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'document_key' => $key, 'document_sha256' => $sha,
                    'document_bytes' => $size, 'document_mime' => $mime,
                    'document_scanned_at' => now(),
                    'submitted_by' => $actor->id, 'status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->audit($actor, 'rights.evidence_submitted', $id, $ip, [
                    'sha256' => $sha, 'title_type' => $data['licensable_type'],
                    'title_id' => $data['licensable_id'],
                ]);
                return (int) $id;
            });
        } catch (Throwable $error) {
            $disk->delete($key);
            throw $error;
        }
    }

    public function download(User $actor, int $id): array
    {
        $this->administrator($actor);
        $e = DB::table('rights_evidence')->where('id', $id)->first();
        abort_unless($e && $e->document_scanned_at && $e->status !== 'revoked', 404);
        $key = (string) $e->document_key;
        abort_unless(preg_match('#\Arights-evidence/[0-9a-f-]{36}\.(pdf|jpg|png)\z#D', $key), 404);
        $disk = Storage::disk('local');
        $path = $disk->path($key);
        $root = realpath($disk->path(''));
        $real = realpath($path);
        abort_unless($root && $real && !is_link($path)
            && str_starts_with($real, $root.DIRECTORY_SEPARATOR)
            && is_file($real) && filesize($real) === (int) $e->document_bytes
            && hash_equals((string) $e->document_sha256, (string) hash_file('sha256', $real)),
            404, 'Evidence integrity changed. Document unavailable.');
        return ['path' => $real, 'mime' => $e->document_mime];
    }

    public function review(User $reviewer, int $id, string $reference, string $expectedSha, ?string $ip): void
    {
        $this->administrator($reviewer);
        abort_unless(preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._\/-]{7,179}\z/D', $reference), 422);
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $expectedSha), 422);
        DB::transaction(function () use ($reviewer, $id, $reference, $expectedSha, $ip): void {
            $e = DB::table('rights_evidence')->where('id', $id)->lockForUpdate()->first();
            abort_unless($e && $e->status === 'pending', 409);
            abort_if((int) $e->submitted_by === (int) $reviewer->id, 403,
                'A different administrator must verify evidence.');
            abort_if(DB::table('media_ingests')
                ->where('ingestable_type', $e->licensable_type)
                ->where('ingestable_id', $e->licensable_id)
                ->where(fn ($q) => $q->where('uploaded_by', $reviewer->id)
                    ->orWhere('approved_by', $reviewer->id))->exists(),
                403, 'The evidence verifier must differ from media uploader and ingest approver.');
            abort_unless(hash_equals((string) $e->document_sha256, $expectedSha)
                && \Illuminate\Support\Carbon::parse($e->ends_at)->isFuture(), 409,
                'Document fingerprint or license validity changed.');
            $disk = Storage::disk('local');
            $file = $disk->path($e->document_key);
            abort_unless(is_file($file) && !is_link($file)
                && hash_equals((string) $e->document_sha256, (string) hash_file('sha256', $file)),
                409, 'Evidence is unavailable or altered.');
            // Reviewer has externally examined the legal license and its scope.
            // This is auditable human attestation, not automatic legal proof.
            DB::table('rights_evidence')->where('id', $id)->where('status', 'pending')->update([
                'status' => 'approved', 'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(), 'review_reference' => $reference, 'updated_at' => now(),
            ]);
            $rights = DB::table('content_rights')
                ->where('licensable_type', $e->licensable_type)
                ->where('licensable_id', $e->licensable_id)
                ->where('rights_reference', $e->rights_reference)->lockForUpdate()->first();
            if ($rights) {
                abort_unless($rights->status === 'pending', 409,
                    'Existing rights record cannot be silently replaced.');
                DB::table('content_rights')->where('id', $rights->id)->update([
                    'status' => 'active', 'starts_at' => $e->starts_at,
                    'ends_at' => $e->ends_at, 'territories' => null, 'updated_at' => now(),
                ]);
            } else {
                DB::table('content_rights')->insert([
                    'licensable_type' => $e->licensable_type,
                    'licensable_id' => $e->licensable_id,
                    'rights_reference' => $e->rights_reference,
                    'territories' => null, 'status' => 'active',
                    'starts_at' => $e->starts_at, 'ends_at' => $e->ends_at,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->audit($reviewer, 'rights.evidence_verified', $id, $ip, [
                'sha256' => $e->document_sha256, 'review_reference' => $reference,
            ]);
        });
    }

    public function revoke(User $actor, int $id, string $reason, ?string $ip): void
    {
        $this->administrator($actor);
        abort_unless(mb_strlen(trim($reason)) >= 8 && mb_strlen($reason) <= 255, 422);
        DB::transaction(function () use ($actor, $id, $reason, $ip): void {
            $e = DB::table('rights_evidence')->where('id', $id)->lockForUpdate()->first();
            abort_unless($e && in_array($e->status, ['pending','approved'], true), 409);
            DB::table('rights_evidence')->where('id', $id)->update([
                'status' => 'revoked', 'revoked_by' => $actor->id, 'revoked_at' => now(),
                'revocation_reason' => trim($reason), 'updated_at' => now(),
            ]);
            DB::table('content_rights')->where('licensable_type', $e->licensable_type)
                ->where('licensable_id', $e->licensable_id)
                ->where('rights_reference', $e->rights_reference)
                ->update(['status' => 'revoked', 'updated_at' => now()]);
            DB::table('media_publication_reviews')
                ->whereIn('media_ingest_id', DB::table('media_ingests')
                    ->select('id')->where('ingestable_type', $e->licensable_type)
                    ->where('ingestable_id', $e->licensable_id))
                ->where('rights_reference', $e->rights_reference)
                ->update(['status' => 'revoked', 'updated_at' => now()]);
            $this->audit($actor, 'rights.evidence_revoked', $id, $ip, ['reason' => trim($reason)]);
        });
    }

    private function audit(User $actor, string $action, int $id, ?string $ip, array $details): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id' => $actor->id, 'action' => $action,
            'resource_type' => 'rights_evidence', 'resource_id' => $id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'ip_address' => $ip, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
