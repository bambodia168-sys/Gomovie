<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Series extends Model
{
    protected $table = 'series';
    protected $fillable = ['tmdb_id', 'title', 'slug', 'overview', 'first_air_date', 'country_code', 'poster_path', 'backdrop_path', 'is_premium', 'status', 'published_at'];
    protected function casts(): array
    {
        return ['is_premium' => 'boolean', 'first_air_date' => 'date', 'published_at' => 'datetime'];
    }
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }
    public function genres(): MorphToMany
    {
        return $this->morphToMany(Genre::class, 'genreable');
    }
    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }
}
