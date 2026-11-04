<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyStatCorrection extends Model
{
    protected $table = 'ff_stat_corrections';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime'];
}
