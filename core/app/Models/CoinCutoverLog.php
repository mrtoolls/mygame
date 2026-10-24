<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinCutoverLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'dry_run' => 'boolean',
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
