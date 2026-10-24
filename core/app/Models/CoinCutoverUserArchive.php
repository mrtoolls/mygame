<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinCutoverUserArchive extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected $casts = [
        'legacy_balance' => 'decimal:8',
        'rate_used' => 'decimal:8',
        'coins_minted' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function cutoverLog()
    {
        return $this->belongsTo(CoinCutoverLog::class, 'coin_cutover_log_id');
    }
}
