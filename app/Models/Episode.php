<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Episode extends Model
{
    protected $fillable = ['season_id', 'episode_number', 'title', 'overview', 'runtime_minutes', 'air_date', 'still_path', 'status', 'published_at'];
    protected function casts(): array
    {
        return ['air_date' => 'date', 'published_at' => 'datetime'];
    }
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'streamable');
    }
}
