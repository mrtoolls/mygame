<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGamePlayer extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['private_state' => 'array', 'last_seen_at' => 'datetime'];

    public function table()
    {
        return $this->belongsTo(TableGame::class, 'table_game_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
