<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaAsset extends Model
{
    protected $fillable = ['streamable_type', 'streamable_id', 'storage_disk', 'manifest_key', 'format', 'status', 'drm_scheme'];
    protected $hidden = ['storage_disk', 'manifest_key'];
    public function streamable(): MorphTo
    {
        return $this->morphTo();
    }
}
