<?php

namespace App\Services;

use App\Models\ChatGroup;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrolment;
use App\Models\TeacherCourse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnrolmentService
{
    /**
     * Put a student on a course. The first time, the system picks a teacher for them
     * and adds them to that teacher's class group and a one-to-one chat with the teacher.
     */
    public function enrol(User $student, Course $course, ?CourseLevel $level = null, ?int $daysPerWeek = null): Enrolment
    {
        $level ??= $course->levels()->first();

        return DB::transaction(function () use ($student, $course, $level, $daysPerWeek) {
            $existing = Enrolment::where('student_id', $student->id)->where('course_id', $course->id)->first();
            if ($existing) {
                return $existing;
            }

            $teacher = $this->pickTeacher($course, $level);

            $enrolment = Enrolment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_level_id' => $level?->id,
                'teacher_id' => $teacher?->id,
                'days_per_week' => $daysPerWeek,
                'status' => 'active',
                'started_on' => now()->toDateString(),
            ]);

            if ($teacher) {
                $this->joinTeacherGroups($enrolment, $teacher);
            }

            return $enrolment;
        });
    }

    /**
     * The teacher of this course and level who has the fewest active students.
     * Ties go to the teacher who joined first, so the choice is predictable.
     */
    public function pickTeacher(Course $course, ?CourseLevel $level): ?User
    {
        $teacherIds = TeacherCourse::where('course_id', $course->id)
            ->where(fn ($q) => $q->whereNull('course_level_id')->when($level, fn ($w) => $w->orWhere('course_level_id', $level->id)))
            ->pluck('teacher_id')
            ->unique();

        return User::whereIn('id', $teacherIds)
            ->where('role', User::ROLE_TEACHER)
            ->where('active', true)
            ->withCount(['teachingEnrolments as load' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('load')
            ->orderBy('id')
            ->first();
    }

    /** An admin moves a student to another teacher. The student moves to that teacher's groups. */
    public function changeTeacher(Enrolment $enrolment, User $teacher): Enrolment
    {
        if (! $teacher->isTeacher()) {
            throw new RuntimeException('Only a teacher can be given students.');
        }

        return DB::transaction(function () use ($enrolment, $teacher) {
            if ($old = $this->classGroup($enrolment->course_id, $enrolment->course_level_id, $enrolment->teacher_id)) {
                $old->members()->detach($enrolment->student_id);
            }

            $enrolment->update(['teacher_id' => $teacher->id]);
            $this->joinTeacherGroups($enrolment, $teacher);

            return $enrolment->refresh();
        });
    }

    public function joinTeacherGroups(Enrolment $enrolment, User $teacher): void
    {
        $course = $enrolment->course;
        $level = $enrolment->level;

        $group = $this->classGroup($course->id, $level?->id, $teacher->id) ?? tap(ChatGroup::create([
            'name' => trim($course->name.' '.($level?->name ?? '').' · '.$teacher->name),
            'kind' => 'class',
            'course_id' => $course->id,
            'course_level_id' => $level?->id,
            'teacher_id' => $teacher->id,
        ]), fn ($g) => $g->members()->attach($teacher->id, ['role' => 'teacher']));

        $group->members()->syncWithoutDetaching([$enrolment->student_id => ['role' => 'member']]);

        $this->directChat($enrolment->student, $teacher);
    }

    public function classGroup(int $courseId, ?int $levelId, ?int $teacherId): ?ChatGroup
    {
        return $teacherId ? ChatGroup::where('kind', 'class')
            ->where('course_id', $courseId)
            ->where('course_level_id', $levelId)
            ->where('teacher_id', $teacherId)
            ->first() : null;
    }

    /** The one-to-one chat between two people, created if needed. */
    public function directChat(User $a, User $b): ChatGroup
    {
        $existing = ChatGroup::where('kind', 'direct')
            ->whereHas('members', fn ($q) => $q->where('users.id', $a->id))
            ->whereHas('members', fn ($q) => $q->where('users.id', $b->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $group = ChatGroup::create(['name' => $a->name.' · '.$b->name, 'kind' => 'direct']);
        $group->members()->attach([
            $a->id => ['role' => $a->isTeacher() ? 'teacher' : 'member'],
            $b->id => ['role' => $b->isTeacher() ? 'teacher' : 'member'],
        ]);

        return $group;
    }
}
