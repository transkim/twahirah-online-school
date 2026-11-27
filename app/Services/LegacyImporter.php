<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrolment;
use App\Models\TeacherCourse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Brings students, teachers and their courses over from the old school portal.
 *
 * The input is what the old portal's own student roster and teacher directory return.
 * Running it again updates the same records instead of making copies.
 */
class LegacyImporter
{
    /** Old portal enrolment status codes. 1 is studying; 2 and 3 both show as "Cancelled" there. */
    private const STATUSES = [1 => 'active', 2 => 'cancelled', 3 => 'cancelled'];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var Collection<int, Course> */
    private Collection $courses;

    /** @var array<string, int> teacher id by normalised name */
    private array $teachersByName = [];

    private string $lockedPassword;

    public function __construct(private EnrolmentService $enrolments) {}

    /**
     * @param  list<array{id: string, title?: string, first_name: string, last_name: string, phone?: string, email?: string, gender?: string, is_deleted?: string, courses?: list<array{name: string, mode: string}>}>  $teachers
     * @param  list<array{SID: string, student_no: string, name: string, phone?: string, gender?: string, admission_date?: string, courses: list<array{course: string, mode: string, status: int, level: ?string, teacher: ?string, seen: ?string, balance: ?int, when: ?string, days: ?string}>}>  $students
     * @return array<string, int>
     */
    public function import(array $teachers, array $students): array
    {
        $this->counts = ['teachers' => 0, 'students' => 0, 'enrolments' => 0, 'active_enrolments' => 0, 'owing_students' => 0, 'owing_ugx' => 0, 'unknown_courses' => 0, 'shared_numbers' => 0];
        $this->courses = Course::with('levels')->get();
        // Nobody knows this password, so imported people cannot sign in until the office sets one.
        $this->lockedPassword = Hash::make(Str::random(40));

        DB::transaction(function () use ($teachers) {
            foreach ($teachers as $row) {
                $this->importTeacher($row);
            }
        });

        foreach (array_chunk($students, 250) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $row) {
                    $this->importStudent($row);
                }
            });
        }

        return $this->counts;
    }

    /**
     * @param  array{id: string, title?: string, first_name: string, last_name: string, phone?: string, email?: string, gender?: string, is_deleted?: string, courses?: list<array{name: string, mode: string}>}  $row
     */
    private function importTeacher(array $row): void
    {
        $name = $this->personName(trim($row['first_name'].' '.$row['last_name']));
        $email = filter_var(trim($row['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;

        $teacher = User::firstOrNew(['role' => User::ROLE_TEACHER, 'legacy_id' => (int) $row['id']]);
        if (! $teacher->exists) {
            $teacher->password = $this->lockedPassword;
        }
        $teacher->fill([
            'name' => $name,
            'phone' => $this->phone($row['phone'] ?? null),
            'email' => $email && ! User::where('email', $email)->whereKeyNot($teacher->id)->exists() ? $email : $teacher->email,
            'gender' => $this->gender($row['gender'] ?? null),
            'active' => ($row['is_deleted'] ?? '0') === '0',
        ])->save();

        foreach ($row['courses'] ?? [] as $course) {
            if ($found = $this->course($course['name'], $course['mode'])) {
                TeacherCourse::firstOrCreate(['teacher_id' => $teacher->id, 'course_id' => $found->id, 'course_level_id' => null]);
            }
        }

        $this->teachersByName[$this->nameKey($row['first_name'].$row['last_name'])] = $teacher->id;
        $this->teachersByName[$this->nameKey($row['last_name'].$row['first_name'])] = $teacher->id;
        $this->counts['teachers']++;
    }

    /**
     * @param  array{SID: string, student_no: string, name: string, phone?: string, gender?: string, admission_date?: string, courses: list<array{course: string, mode: string, status: int, level: ?string, teacher: ?string, seen: ?string, balance: ?int, when: ?string, days: ?string}>}  $row
     */
    private function importStudent(array $row): void
    {
        $courses = $row['courses'] ?? [];

        $student = User::firstOrNew(['role' => User::ROLE_STUDENT, 'legacy_id' => (int) $row['SID']]);
        if (! $student->exists) {
            $student->password = $this->lockedPassword;
        }
        $student->fill([
            'name' => $this->personName($row['name']),
            'student_number' => $this->studentNumber($row['student_no'] ?: null, (int) $row['SID'], $student),
            'phone' => $this->phone($row['phone'] ?? null),
            'gender' => $this->gender($row['gender'] ?? null),
            'joined_on' => $this->date($row['admission_date'] ?? null),
        ])->save();
        $this->counts['students']++;

        $owing = 0;
        $isStudying = false;
        foreach ($courses as $legacy) {
            $course = $this->course($legacy['course'], $legacy['mode']);
            if (! $course) {
                $this->counts['unknown_courses']++;

                continue;
            }

            $status = self::STATUSES[(int) $legacy['status']] ?? 'cancelled';
            $balance = (int) ($legacy['balance'] ?? 0);
            $teacherId = $this->teachersByName[$this->nameKey((string) $legacy['teacher'])] ?? null;
            $days = trim((string) ($legacy['days'] ?? ''));

            $enrolment = Enrolment::updateOrCreate(['student_id' => $student->id, 'course_id' => $course->id], [
                'course_level_id' => $this->level($course, $legacy['level'] ?? null)?->id,
                'teacher_id' => $teacherId,
                'days_per_week' => $days === '' ? null : count(array_filter(array_map('trim', explode(',', $days)))),
                'status' => $status,
                'started_on' => $this->date($row['admission_date'] ?? null) ?? now()->toDateString(),
                'ended_on' => $status === 'active' ? null : $this->date($legacy['when'] ?? null),
                'last_seen_on' => $this->date($legacy['seen'] ?? null),
                'legacy_balance_ugx' => $balance,
            ]);
            $this->counts['enrolments']++;

            if ($status === 'active') {
                $this->counts['active_enrolments']++;
                $isStudying = true;
                if ($teacherId) {
                    $this->enrolments->joinTeacherGroups($enrolment, User::find($teacherId));
                }
            }

            $owing += min(0, $balance);
        }

        // A student with no course they are studying now keeps their record but is switched off.
        $student->update(['active' => $isStudying]);

        if ($owing < 0) {
            $this->counts['owing_students']++;
            $this->counts['owing_ugx'] += -$owing;
        }
    }

    /**
     * A few old records share a student number. The first keeps it; the others get their old id added, such as "T/22/3091-3092".
     */
    private function studentNumber(?string $number, int $legacyId, User $student): ?string
    {
        if ($number === null || ! User::where('student_number', $number)->whereKeyNot($student->id)->exists()) {
            return $number;
        }

        $this->counts['shared_numbers']++;

        return $number.'-'.$legacyId;
    }

    /** Match the old portal's course name and mode (WHATSAPP or ZOOM) to a course here. */
    private function course(string $name, string $mode): ?Course
    {
        $mode = strtoupper(trim($mode)) === 'ZOOM' ? 'zoom' : 'chat';
        $key = $this->nameKey($name);

        return $this->courses->first(fn (Course $course): bool => $course->mode === $mode && $this->nameKey($course->name) === $key);
    }

    private function level(Course $course, ?string $name): ?CourseLevel
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return $course->levels->first(fn (CourseLevel $level): bool => $this->nameKey($level->name) === $this->nameKey($name));
    }

    private function nameKey(string $name): string
    {
        return preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($name)));
    }

    /** "KATENDE  amir" becomes "Katende Amir". */
    private function personName(string $name): string
    {
        return Str::title(Str::lower(preg_replace('/\s+/', ' ', trim($name))));
    }

    private function phone(?string $phone): ?string
    {
        $phone = preg_replace('/[^\d+]/', '', (string) $phone);

        return strlen(ltrim($phone, '+')) >= 7 ? $phone : null;
    }

    private function gender(?string $gender): ?string
    {
        return match (Str::lower(trim((string) $gender))) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            default => null,
        };
    }

    private function date(?string $date): ?string
    {
        return $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date !== '0000-00-00' ? $date : null;
    }
}
