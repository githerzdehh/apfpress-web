<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CatalogItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'published_at' => 'datetime',
            'metadata_flags' => 'array',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where(function (Builder $query) {
            $query->whereNull('published_at')->orWhere('published_at', '<=', now());
        });
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }

    public function contributors(): BelongsToMany
    {
        return $this->belongsToMany(Contributor::class, 'catalog_item_contributors')
            ->withPivot(['role', 'position'])
            ->orderByPivot('position');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'catalog_item_media')
            ->withPivot(['role', 'position'])
            ->orderByPivot('position');
    }

    public function bookDetails(): HasOne
    {
        return $this->hasOne(BookDetail::class);
    }

    public function serviceDetails(): HasOne
    {
        return $this->hasOne(ServiceDetail::class);
    }

    public function getCoverAttribute(): ?MediaAsset
    {
        return $this->media->firstWhere('pivot.role', 'cover');
    }
}
