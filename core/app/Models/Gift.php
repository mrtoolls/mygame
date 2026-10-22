<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    use GlobalStatus;

    protected $guarded = ['id'];

    public function imageUrl(): string
    {
        return getImage((string) $this->image);
    }

    // The matching .webp sibling of a PNG/JPEG image, if one actually exists on disk - used to
    // build a <picture> source without assuming every gift (including admin-uploaded ones) has one.
    public function webpImageUrl(): ?string
    {
        $path = (string) $this->image;
        if ($path === '' || !preg_match('/\.(png|jpe?g)$/i', $path)) return null;
        $webpPath = preg_replace('/\.(png|jpe?g)$/i', '.webp', $path);
        return file_exists($webpPath) ? getImage($webpPath) : null;
    }
}
