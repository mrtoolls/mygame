<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGameRematch extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['expires_at' => 'datetime'];

    public function originalTable()
    {
        return $this->belongsTo(TableGame::class, 'original_table_game_id');
    }

    public function newTable()
    {
        return $this->belongsTo(TableGame::class, 'new_table_game_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
