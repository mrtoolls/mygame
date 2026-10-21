<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class NewsPost extends Model
{
    use GlobalStatus;

    protected $guarded = ['id'];

    public function scopePublished($query)
    {
        return $query->where('status', \App\Constants\Status::ENABLE);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? getImage('assets/images/news/' . $this->image) : null;
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'post';
        $slug = $base;
        $i    = 1;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . ++$i;
        }
        return $slug;
    }
}
