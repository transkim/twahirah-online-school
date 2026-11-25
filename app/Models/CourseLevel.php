<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseLevel extends Model
{
    protected $guarded = ['id'];

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
}
