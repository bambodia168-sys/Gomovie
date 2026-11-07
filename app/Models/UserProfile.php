<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = ['user_id', 'name', 'slug', 'is_kids'];
    protected function casts(): array
    {
        return ['is_kids' => 'boolean'];
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
