<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Homework extends Model
{
    protected $table = 'homework';

    protected $guarded = ['id'];

    protected $casts = ['due_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }
}
