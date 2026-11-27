<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_FINANCE = 'finance';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT = 'student';

    protected $fillable = [
        'name', 'username', 'role', 'email', 'phone', 'password', 'student_number',
        'timezone', 'country', 'active', 'legacy_id', 'gender', 'joined_on',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Defaults that match the database, so new models behave the same before and after saving. */
    protected $attributes = [
        'role' => self::ROLE_STUDENT,
        'active' => true,
        'timezone' => 'Africa/Kampala',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'student_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'student_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'student_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    /** Students taught by this teacher. */
    public function teachingEnrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'teacher_id');
    }

    public function teacherCourses(): HasMany
    {
        return $this->hasMany(TeacherCourse::class, 'teacher_id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class, 'teacher_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function chatGroups(): BelongsToMany
    {
        return $this->belongsToMany(ChatGroup::class, 'chat_group_members')->withPivot('role', 'muted', 'last_read_at')->withTimestamps();
    }
}
