<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaComment extends Model
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

    public function parent()
    {
        return $this->belongsTo(ArenaComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ArenaComment::class, 'parent_id')->oldest();
    }
}
