<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaPost extends Model
{
    protected $guarded = ['id'];

    public const TYPES = ['general', 'win', 'challenge', 'question'];

    public const BACKGROUNDS = ['violet', 'sunset', 'forest', 'ocean', 'rose'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function linkedGameLog()
    {
        return $this->belongsTo(GameLog::class, 'linked_game_log_id');
    }

    public function comments()
    {
        return $this->hasMany(ArenaComment::class, 'post_id')->whereNull('parent_id')->latest();
    }

    public function votes()
    {
        return $this->hasMany(ArenaPostVote::class, 'post_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? getImage('assets/images/arena-posts/' . $this->image) : null;
    }
}
