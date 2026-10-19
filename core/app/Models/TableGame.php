<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TableGame extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id'];

    protected $casts = [
        'state' => 'array',
        'settings' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'turn_started_at' => 'datetime',
        'turn_deadline_at' => 'datetime',
        'join_deadline_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $table) {
            $table->id ??= (string) Str::uuid();
        });
    }

    public function players()
    {
        return $this->hasMany(TableGamePlayer::class, 'table_game_id')->orderBy('seat');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function moves()
    {
        return $this->hasMany(TableGameMove::class, 'table_game_id');
    }
}
