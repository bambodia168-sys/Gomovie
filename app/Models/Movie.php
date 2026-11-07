<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Movie extends Model
{
    protected $fillable = ['tmdb_id', 'title', 'slug', 'overview', 'release_year', 'runtime_minutes', 'country_code', 'poster_path', 'backdrop_path', 'is_premium', 'status', 'published_at'];
    protected function casts(): array
    {
        return ['is_premium' => 'boolean', 'published_at' => 'datetime'];
    }
    public function genres(): MorphToMany
    {
        return $this->morphToMany(Genre::class, 'genreable');
    }
    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }
    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'streamable');
    }
}
