<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['last_message_at' => 'datetime'];

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function isParticipant(string $type, int $id): bool
    {
        return ($this->type_a === $type && (int) $this->id_a === $id)
            || ($this->type_b === $type && (int) $this->id_b === $id);
    }

    /** The {type, id} of whichever side is NOT the given participant. */
    public function otherParticipant(string $type, int $id): array
    {
        if ($this->type_a === $type && (int) $this->id_a === $id) {
            return [$this->type_b, (int) $this->id_b];
        }
        return [$this->type_a, (int) $this->id_a];
    }

    public function scopeForParticipant($query, string $type, int $id)
    {
        return $query->where(function ($q) use ($type, $id) {
            $q->where(fn ($q2) => $q2->where('type_a', $type)->where('id_a', $id))
                ->orWhere(fn ($q2) => $q2->where('type_b', $type)->where('id_b', $id));
        });
    }

    /**
     * Finds (or creates) the one conversation between two participants, stored in a canonical
     * order so the pair always resolves to the same row regardless of who's asking or who
     * started it - a plain string comparison on "{type}:{id}" is a stable, deterministic sort.
     */
    public static function between(string $typeA, int $idA, string $typeB, int $idB): self
    {
        $a = $typeA . ':' . $idA;
        $b = $typeB . ':' . $idB;
        if ($a > $b) {
            [$typeA, $idA, $typeB, $idB] = [$typeB, $idB, $typeA, $idA];
        }

        return static::firstOrCreate(
            ['type_a' => $typeA, 'id_a' => $idA, 'type_b' => $typeB, 'id_b' => $idB]
        );
    }
}
