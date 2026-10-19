<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\UserNotify;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public function currency()
    {
        return $this->belongsTo(\App\Models\Currency::class);
    }

    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class);
    }
    use HasApiTokens, UserNotify;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'ver_code',
        'balance',
        'kyc_data',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'kyc_data'          => 'object',
        'ver_code_send_at'  => 'datetime',
    ];

    protected $data = [
        'data' => 1,
    ];

    public function loginLogs()
    {
        return $this->hasMany(UserLogin::class);
    }

    public function walletCurrency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function residenceCountry()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderBy('id', 'desc');
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class)->where('status', '!=', Status::PAYMENT_INITIATE);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class)->where('status', '!=', Status::PAYMENT_INITIATE);
    }

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function referrer()
    {
        return $this->belongsTo(self::class, 'ref_by');
    }

    public function referrals()
    {
        return $this->hasMany(self::class, 'ref_by');
    }

    public function allReferrals()
    {
        return $this->referrals()->with('referrer');
    }

    public function gameLogs()
    {
        return $this->hasMany(GameLog::class);
    }

    public function rakebackClaims()
    {
        return $this->hasMany(VipRakebackClaim::class);
    }

    /**
     * Lifetime real-money wagering (demo play excluded) - the single figure
     * VIP tier thresholds and rakeback are both computed from.
     */
    public function totalWagered()
    {
        return (float) $this->gameLogs()->where('demo_play', 0)->sum('invest');
    }

    public function vipTier()
    {
        $wagered = $this->totalWagered();
        return VipTier::active()->where('min_wagered', '<=', $wagered)->orderByDesc('min_wagered')->first();
    }

    public function nextVipTier()
    {
        $wagered = $this->totalWagered();
        return VipTier::active()->where('min_wagered', '>', $wagered)->orderBy('min_wagered')->first();
    }

    /**
     * Wagering in the trailing 24 hours - the figure the Bonuses page shows
     * next to the rakeback slider.
     */
    public function wageredLast24Hours()
    {
        return (float) $this->gameLogs()->where('demo_play', 0)->where('created_at', '>=', now()->subDay())->sum('invest');
    }

    /**
     * Rakeback accrues on wagering since the user's last claim (not lifetime
     * wagering) so it behaves like the "daily" rakeback it's presented as,
     * without double-paying out the same wagering on a second claim. Never
     * trust a claimed amount from the client - always recompute here.
     */
    public function rakebackClaimable()
    {
        $tier = $this->vipTier();
        if (!$tier || $tier->rakeback_percent <= 0) {
            return 0.0;
        }
        $query = $this->gameLogs()->where('demo_play', 0);
        if ($this->last_rakeback_claim_at) {
            $query->where('created_at', '>', $this->last_rakeback_claim_at);
        }
        $wageredSinceClaim = (float) $query->sum('invest');
        return max(0, round($wageredSinceClaim * ((float) $tier->rakeback_percent / 100), 8));
    }

    public function fullname(): Attribute
    {
        return new Attribute(
            get: fn() => $this->firstname . ' ' . $this->lastname,
        );
    }

    public function mobileNumber(): Attribute
    {
        return new Attribute(
            get: fn() => $this->dial_code . $this->mobile,
        );
    }

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('status', Status::USER_ACTIVE)->where('ev', Status::VERIFIED)->where('sv', Status::VERIFIED);
    }

    public function scopeBanned($query)
    {
        return $query->where('status', Status::USER_BAN);
    }

    public function scopeEmailUnverified($query)
    {
        return $query->where('ev', Status::UNVERIFIED);
    }

    public function scopeMobileUnverified($query)
    {
        return $query->where('sv', Status::UNVERIFIED);
    }

    public function scopeKycUnverified($query)
    {
        return $query->where('kv', Status::KYC_UNVERIFIED);
    }

    public function scopeKycPending($query)
    {
        return $query->where('kv', Status::KYC_PENDING);
    }

    public function scopeEmailVerified($query)
    {
        return $query->where('ev', Status::VERIFIED);
    }

    public function scopeMobileVerified($query)
    {
        return $query->where('sv', Status::VERIFIED);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance', '>', 0);
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }
}
