<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyAuditLog extends Model
{
    protected $table = 'ff_audit_log';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    public static function record(string $action, ?int $adminId = null, ?string $subjectType = null, ?int $subjectId = null, array $payload = []): void
    {
        static::create([
            'admin_id' => $adminId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
