<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    protected $guarded = [];

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'remote') {
            return $this->source_url ?? $this->path;
        }

        if ($this->path && Storage::disk($this->disk)->exists($this->path)) {
            return Storage::disk($this->disk)->url($this->path);
        }

        return $this->source_url ?? '';
    }
}
