<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaReaction extends Model
{
    protected $guarded = ['id'];

    public const EMOJIS = ['👍', '❤️', '🔥', '😂', '😮', '😢'];

    public const TYPES = [
        'post'    => ArenaPost::class,
        'comment' => ArenaComment::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Groups raw reaction rows for a reactable into ['emoji' => count, ...] plus the viewer's own pick. */
    public static function summaryFor(string $reactableType, int $reactableId, ?int $viewerId = null): array
    {
        $rows = static::where('reactable_type', $reactableType)->where('reactable_id', $reactableId)->get();
        $counts = [];
        foreach (self::EMOJIS as $emoji) {
            $counts[$emoji] = 0;
        }
        $mine = null;
        foreach ($rows as $row) {
            $counts[$row->emoji] = ($counts[$row->emoji] ?? 0) + 1;
            if ($viewerId && (int) $row->user_id === $viewerId) {
                $mine = $row->emoji;
            }
        }
        return ['counts' => $counts, 'mine' => $mine, 'total' => $rows->count()];
    }
}
