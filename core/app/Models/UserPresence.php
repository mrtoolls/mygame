<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPresence extends Model
{
    protected $table = 'user_presence';

    // The table's primary key IS user_id (no separate auto-increment id column exists - see the
    // migration) - without declaring this, Eloquent defaults to 'id' and every find()/save() call
    // generates a query against a column that doesn't exist at all, failing on every call.
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $guarded = ['user_id'];

    public $timestamps = false;

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
