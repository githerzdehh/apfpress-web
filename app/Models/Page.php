<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['content_blocks' => 'array', 'published_at' => 'datetime'];
    }
}
