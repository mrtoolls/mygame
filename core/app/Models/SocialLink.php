<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNotNull('url')->where('url', '!=', '')->orderBy('sort_order');
    }

    /** Icon class for the ones Phosphor already ships; 'x' is drawn as an inline SVG instead -
     *  Phosphor's X/Twitter glyph is still the old bird mark, not the current X logo. */
    public function iconClass(): ?string
    {
        return match ($this->platform) {
            'facebook' => 'ph-fill ph-facebook-logo',
            'instagram' => 'ph-fill ph-instagram-logo',
            'discord' => 'ph-fill ph-discord-logo',
            'tiktok' => 'ph-fill ph-tiktok-logo',
            default => null,
        };
    }
}
