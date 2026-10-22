<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionPrize extends Model
{
    protected $guarded = ['id'];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }
}
