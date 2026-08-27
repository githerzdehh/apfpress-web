<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $guarded = [];

    public function catalogItems(): BelongsToMany
    {
        return $this->belongsToMany(CatalogItem::class);
    }
}
