<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftTransaction extends Model
{
    protected $guarded = ['id'];

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
