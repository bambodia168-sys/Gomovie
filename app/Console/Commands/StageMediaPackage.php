<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Media\PrivateMediaStagingPublisher;
use Illuminate\Console\Command;

final class StageMediaPackage extends Command
{
    protected $signature = 'gomovie:stage-media
        {ingest_id : Independently reviewed encoded ingest}
        {--publisher= : Verified administrator email distinct from the reviewer}';
    protected $description = 'Two-person-gated private S3/R2 staging (not playback publication)';

    public function handle(PrivateMediaStagingPublisher $staging): int
    {
        $id = filter_var($this->argument('ingest_id'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $email = $this->option('publisher');
        if ($id === false || !is_string($email) || $email === '') {
            $this->error('A valid ingest ID and a separate verified publisher email are required.');
            return self::FAILURE;
        }

        $actor = User::query()->where('email', $email)->first();
        if (!$actor) {
            $this->error('Publisher not found.');
            return self::FAILURE;
        }
        if (!$this->confirm('Stage this reviewed package in PRIVATE storage? No asset will be made playable.', false)) {
            return self::FAILURE;
        }
        $batchId = $staging->stage((int) $id, $actor);
        $this->info('Private staging batch #'.$batchId.' verified. Playback remains disabled.');
        return self::SUCCESS;
    }
}
