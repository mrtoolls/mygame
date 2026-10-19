<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGameSetting extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['enabled' => 'boolean'];
}
