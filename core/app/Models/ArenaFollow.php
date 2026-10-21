<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaFollow extends Model
{
    protected $guarded = ['id'];

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function followed()
    {
        return $this->belongsTo(User::class, 'followed_id');
    }
}
