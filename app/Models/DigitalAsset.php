<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_current' => 'boolean', 'version' => 'integer'];
    }

    public function offering()
    {
        return $this->belongsTo(Offering::class);
    }
}
