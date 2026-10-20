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
}
