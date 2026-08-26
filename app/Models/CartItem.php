<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $guarded = [];

    public function offering()
    {
        return $this->belongsTo(Offering::class);
    }
}
