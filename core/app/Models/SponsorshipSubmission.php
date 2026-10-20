<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorshipSubmission extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moderator()
    {
        return $this->belongsTo(Admin::class, 'moderated_by');
    }
}
