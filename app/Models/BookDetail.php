<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookDetail extends Model
{
    protected $primaryKey = 'catalog_item_id';
    public $incrementing = false;
    protected $guarded = [];
}
