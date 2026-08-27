<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookEdition extends Model
{
    protected $primaryKey = 'offering_id';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['publication_date' => 'date'];
    }
}
