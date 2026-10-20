<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGameViewer extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
