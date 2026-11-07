<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['user_id', 'plan_name', 'max_concurrent_streams', 'status', 'provider_name', 'provider_subscription_id', 'starts_at', 'ends_at', 'canceled_at'];
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'canceled_at' => 'datetime'];
    }
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }
}
