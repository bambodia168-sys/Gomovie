<?php

use App\Support\SiteSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

foreach (['default', 'gomovie-media'] as $queue) {
    Schedule::command('gomovie:queue-probe '.$queue)->everyFiveMinutes()
        ->when(fn () => SiteSettings::get('queue_probes_enabled') === true)
        ->withoutOverlapping();
}
Schedule::command('gomovie:prune-queue-probes')->dailyAt('03:15')->withoutOverlapping();
