<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinVendorApplication extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'identity_data' => 'encrypted:array',
        'review_checklist' => 'array',
        'consent_identity' => 'boolean',
        'consent_p2p_risk' => 'boolean',
        'duplicate_flag' => 'boolean',
        'challenge_expires_at' => 'datetime',
        'challenge_used_at' => 'datetime',
        'video_recorded_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'retention_delete_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function vendor() { return $this->hasOne(CoinVendor::class, 'application_id'); }
}
