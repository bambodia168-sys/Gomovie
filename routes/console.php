<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue probes run only when explicitly enabled by a reviewed operator.
if (config('gomovie.queue_probes_enabled') === true) {
    \Illuminate\Support\Facades\Schedule::command('gomovie:queue-probe default')
        ->everyFiveMinutes()->withoutOverlapping();
    \Illuminate\Support\Facades\Schedule::command('gomovie:queue-probe gomovie-media')
        ->everyFiveMinutes()->withoutOverlapping();
}
\Illuminate\Support\Facades\Schedule::command('gomovie:prune-queue-probes')
    ->dailyAt('03:15')->withoutOverlapping();

// Only expired private chunk fragments; never original approved media.
\Illuminate\Support\Facades\Schedule::command('gomovie:prune-resumable-uploads')
    ->dailyAt('03:35')->withoutOverlapping();
