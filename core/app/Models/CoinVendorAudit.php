<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinVendorAudit extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime'];

    public static function record(string $action, ?int $applicationId = null, ?int $vendorId = null, ?int $adminId = null, ?string $subject = null, ?string $details = null): self
    {
        return static::create([
            'application_id' => $applicationId,
            'vendor_id' => $vendorId,
            'admin_id' => $adminId,
            'action' => $action,
            'subject' => $subject,
            'details' => $details,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
