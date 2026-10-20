<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGiftInventory extends Model
{
    protected $table = 'user_gift_inventory';

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }
}
