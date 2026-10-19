<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGameUserLimit extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['banned' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
