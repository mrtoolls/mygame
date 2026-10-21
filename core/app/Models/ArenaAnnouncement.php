<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class ArenaAnnouncement extends Model
{
    use GlobalStatus;

    protected $guarded = ['id'];

    protected $casts = [
        'ends_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', \App\Constants\Status::ENABLE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function imageUrl(): ?string
    {
        return $this->image ? getImage('assets/images/arena-announcements/' . $this->image) : null;
    }
}
