<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaNotification extends Model
{
    protected $guarded = ['id'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /** Fires a notification, silently skipping self-notifications (e.g. commenting on your own post). */
    public static function fire(int $userId, string $type, ?int $actorId, ?string $subjectType, ?int $subjectId, string $message): void
    {
        if ($actorId && $actorId === $userId) {
            return;
        }
        static::create([
            'user_id'      => $userId,
            'type'         => $type,
            'actor_id'     => $actorId,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'message'      => $message,
        ]);
    }
}
