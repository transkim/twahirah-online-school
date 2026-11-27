<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    public function levels(): HasMany
    {
        return $this->hasMany(CourseLevel::class)->orderBy('position');
    }

    public function teacherCourses(): HasMany
    {
        return $this->hasMany(TeacherCourse::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }
}
