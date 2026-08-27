<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $primaryKey = 'offering_id';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['track_inventory' => 'boolean', 'allow_backorder' => 'boolean'];
    }
}
