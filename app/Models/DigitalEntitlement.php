<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalEntitlement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function asset()
    {
        return $this->belongsTo(DigitalAsset::class, 'digital_asset_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function isAccessible(): bool
    {
        return $this->revoked_at === null
            && $this->starts_at->isPast()
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->asset?->active;
    }
}
