<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeBand extends Model
{
    protected $guarded = ['id'];

    /** The band a score falls in, such as "A" for 85. */
    public static function for(?int $score): ?self
    {
        return $score === null ? null : static::where('min_score', '<=', $score)->orderByDesc('min_score')->first();
    }
}
