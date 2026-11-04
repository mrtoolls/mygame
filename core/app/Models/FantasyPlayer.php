<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyPlayer extends Model
{
    protected $table = 'ff_players';
    protected $guarded = ['id'];
    protected $casts = [
        'rating' => 'decimal:2',
        'price' => 'decimal:1',
        'minutes_tendency' => 'decimal:3',
        'is_active' => 'boolean',
        'status_until' => 'date',
    ];

    public function club()
    {
        return $this->belongsTo(FantasyClub::class, 'club_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'fit' || ($this->status_until && $this->status_until->isPast());
    }
}
