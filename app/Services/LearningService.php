<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Notifications\SchoolNotice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Who can see and add lessons and homework. Students see their own courses while their subscription
 * is active, teachers see the courses they teach, and the office sees everything.
 */
class LearningService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function isStaff(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true);
    }

    /**
     * The course and level pairs the person can see. A null level means every level of that course.
     *
     * @return Collection<int, array{course_id: int, level_id: ?int}>|null null means everything
     */
    public function scopeFor(User $user): ?Collection
    {
        if ($this->isStaff($user)) {
            return null;
        }

        if ($user->isTeacher()) {
            return TeacherCourse::where('teacher_id', $user->id)->get()
                ->map(fn (TeacherCourse $tc): array => ['course_id' => $tc->course_id, 'level_id' => $tc->course_level_id]);
        }

        return Enrolment::with('course')->where('student_id', $user->id)->where('status', 'active')->get()
            ->filter(fn (Enrolment $e): bool => $this->subscriptions->hasAccess($user, $e->course->mode))
            ->map(fn (Enrolment $e): array => ['course_id' => $e->course_id, 'level_id' => $e->course_level_id])
            ->values();
    }

    /** Limit a lessons or homework query to what the person can see. */
    public function limit(Builder $query, User $user): Builder
    {
        $scope = $this->scopeFor($user);
        if ($scope === null) {
            return $query;
        }
        if ($scope->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($scope, $user) {
            foreach ($scope as $pair) {
                $q->orWhere(function (Builder $w) use ($pair, $user) {
                    $w->where('course_id', $pair['course_id']);
                    if ($user->isStudent()) {
                        // Students see lessons for every level of the course and the ones for their own level.
                        $w->where(fn ($l) => $l->whereNull('course_level_id')->orWhere('course_level_id', $pair['level_id']));
                    } elseif ($pair['level_id'] !== null) {
                        $w->where('course_level_id', $pair['level_id']);
                    }
                });
            }
        });
    }

    public function canAddTo(User $user, int $courseId): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && TeacherCourse::where('teacher_id', $user->id)->where('course_id', $courseId)->exists());
    }

    /**
     * @return array{attachment_path: string, attachment_name: string, attachment_mime: string}|array{}
     */
    public function store(?UploadedFile $file, string $folder): array
    {
        if (! $file) {
            return [];
        }

        return [
            'attachment_path' => $file->store($folder, 'local'),
            'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 191),
            'attachment_mime' => (string) $file->getMimeType(),
        ];
    }

    /** Tell the active students of a course (and level) about something new. */
    public function notifyStudents(int $courseId, ?int $levelId, SchoolNotice $notice): void
    {
        $students = User::where('active', true)->whereHas('enrolments', fn ($q) => $q->where('course_id', $courseId)->where('status', 'active')
            ->when($levelId, fn ($w) => $w->where('course_level_id', $levelId)))->get();

        Notification::send($students, $notice);
    }
}
