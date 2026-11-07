<?php

namespace App\Providers;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Series;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Never deserialize arbitrary class names from polymorphic database records.
        Relation::enforceMorphMap([
            'movie' => Movie::class,
            'series' => Series::class,
            'episode' => Episode::class,
        ]);
    }
}
