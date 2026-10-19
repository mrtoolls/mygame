<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserFeedback extends Model
{
    protected $table = 'user_feedback';
    protected $guarded = ['id'];
    protected $casts = [
        'rating' => 'integer',
        'player_since' => 'integer',
        'public_consent' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function moderator() { return $this->belongsTo(Admin::class, 'moderated_by'); }
    public function scopeApproved(Builder $query): Builder { return $query->where('status', 'approved'); }
}
