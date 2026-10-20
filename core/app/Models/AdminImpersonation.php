<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminImpersonation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
