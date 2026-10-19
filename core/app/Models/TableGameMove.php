<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGameMove extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['public_data' => 'array', 'created_at' => 'datetime'];

    public function table()
    {
        return $this->belongsTo(TableGame::class, 'table_game_id');
    }
}
