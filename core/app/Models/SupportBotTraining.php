<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportBotTraining extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function triggerList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->trigger_phrases))));
    }
}
