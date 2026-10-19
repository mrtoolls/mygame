<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGameInvite extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function table()
    {
        return $this->belongsTo(TableGame::class, 'table_game_id');
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee()
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
