<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Media\MediaPublicationReviewer;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ReviewMediaPackage extends Command
{
    protected $signature = 'gomovie:review-media
        {ingest_id : Encoded media ingest ID}
        {--reviewer= : Existing verified independent administrator email}
        {--rights-ref= : Exact active worldwide rights record reference}
        {--scan-ref= : Independent clean malware scan evidence reference}
        {--quality-ref= : Independent HLS quality review evidence reference}
        {--manifest-sha= : SHA-256 hash from the operator reviewed master playlist}';
    protected $description = 'Record a second-operator HLS review and evidence without enabling playback';

    public function handle(MediaPublicationReviewer $reviewer): int
    {
        $id = filter_var($this->argument('ingest_id'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $email = $this->option('reviewer');
        if ($id === false || !is_string($email) || $email === '') {
            $this->error('A valid ingest ID and existing independent reviewer email are required.');
            return self::FAILURE;
        }

        $person = User::query()->where('email', $email)->first();
        if (!$person) {
            $this->error('Reviewer not found.');
            return self::FAILURE;
        }
        if (!$this->confirm('Record an immutable independent publication review? It does NOT enable playback.', false)) {
            return self::FAILURE;
        }

        $reviewId = $reviewer->review(
            (int) $id, $person, (string) $this->option('rights-ref'),
            (string) $this->option('scan-ref'), (string) $this->option('quality-ref'),
            (string) $this->option('manifest-sha')
        );
        $this->info('Recorded media publication review #'.$reviewId.'; private media remains unpublished.');
        return self::SUCCESS;
    }
}
