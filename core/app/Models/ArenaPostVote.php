<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaPostVote extends Model
{
    protected $guarded = ['id'];

    public function post()
    {
        return $this->belongsTo(ArenaPost::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
