<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offering extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function bookEdition(): HasOne
    {
        return $this->hasOne(BookEdition::class);
    }

    public function productVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function digitalAssets(): HasMany
    {
        return $this->hasMany(DigitalAsset::class);
    }

    public function getFormattedPriceAttribute(): ?string
    {
        if ($this->price_amount === null) {
            return null;
        }

        return number_format($this->price_amount / 100, 2).' '.$this->currency;
    }

    public function isAvailable(): bool
    {
        if (! $this->active || $this->purchase_mode !== 'online' || $this->price_amount === null || ! $this->sku) {
            return false;
        }

        if (! $this->inventory?->track_inventory) {
            return true;
        }

        return $this->inventory->allow_backorder || ($this->inventory->on_hand - $this->inventory->reserved) > 0;
    }
}
