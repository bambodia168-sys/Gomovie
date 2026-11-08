<?php

namespace App\Console\Commands;

use App\Jobs\TranscodeApprovedIngest;
use App\Services\Media\TranscodeGate;
use Illuminate\Console\Command;

class QueueApprovedIngest extends Command
{
    protected $signature = 'gomovie:queue-encoding {ingest_id : Independently approved private video ID}';
    protected $description = 'Queue vetted HLS encoding on an isolated worker, without publishing video';

    public function handle(TranscodeGate $gate): int
    {
        $id = filter_var($this->argument('ingest_id'), FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1]]);
        if ($id === false) {
            $this->error('Enter a positive ingest ID.');
            return self::FAILURE;
        }

        $gate->validate((int) $id);
        if (!$this->confirm('Queue private HLS encoding for verified ingest '.$id.'?', false)) {
            $this->warn('No encoding job queued.');
            return self::FAILURE;
        }
        TranscodeApprovedIngest::dispatch((int) $id);
        $this->info('Encoding queued on gomovie-media; media remains private and unpublished.');
        return self::SUCCESS;
    }
}
