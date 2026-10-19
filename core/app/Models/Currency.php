<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_update' => 'boolean',
        'rate_to_usd' => 'decimal:12',
        'last_updated_at' => 'datetime',
    ];

    public function countries()
    {
        return $this->hasMany(Country::class);
    }

    public function rateLogs()
    {
        return $this->hasMany(CurrencyRateLog::class)->latest();
    }

    public function formatMinor(int|string $minor, bool $withCode = false): string
    {
        $formatted = app(\App\Support\Money::class)->formatMinor((string) $minor, (int) $this->decimals);
        $amount = $this->symbol_position === 'right' ? $formatted . ' ' . $this->symbol : $this->symbol . $formatted;
        return $withCode ? $amount . ' ' . $this->code : $amount;
    }
}
