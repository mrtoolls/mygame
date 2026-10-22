<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionAuditLog extends Model
{
    protected $table = 'competition_audit_log';
    protected $guarded = ['id'];

    protected $casts = [
        'details' => 'array',
    ];
}
