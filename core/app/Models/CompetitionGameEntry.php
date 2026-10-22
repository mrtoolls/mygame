<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionGameEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opponent_ids' => 'array',
        'finished_at' => 'datetime',
        'counted_at' => 'datetime',
        'fully_counted' => 'boolean',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tableGame()
    {
        return $this->belongsTo(TableGame::class, 'table_game_id');
    }
}
