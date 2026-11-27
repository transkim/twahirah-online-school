<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['published' => 'boolean', 'gives_certificate' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
