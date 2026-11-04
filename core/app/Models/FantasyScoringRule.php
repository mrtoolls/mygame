<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyScoringRule extends Model
{
    protected $table = 'ff_scoring_rules';
    protected $guarded = ['id'];
    protected $casts = ['value' => 'decimal:2'];
}
