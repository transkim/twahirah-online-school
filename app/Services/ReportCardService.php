<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Enrolment;
use App\Models\GradeBand;
use App\Models\Mark;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A report card: for each course, the average of each kind of mark, a weighted total and its grade,
 * and how many days the student was present.
 */
class ReportCardService
{
    /**
     * @return Collection<int, array{course: string, level: ?string, teacher: ?string, averages: array<string, ?int>, total: ?int, grade: ?string, comment: ?string, present: int, marks: Collection<int, Mark>}>
     */
    public function for(User $student, Carbon $from, Carbon $to): Collection
    {
        $weights = config('school.mark_weights');

        return Enrolment::with('course', 'level', 'teacher')->where('student_id', $student->id)->get()->map(function (Enrolment $e) use ($student, $from, $to, $weights) {
            $marks = Mark::where('student_id', $student->id)->where('course_id', $e->course_id)
                ->whereDate('given_on', '>=', $from->toDateString())->whereDate('given_on', '<=', $to->toDateString())->orderBy('given_on')->get();

            $averages = [];
            foreach (array_keys($weights) as $kind) {
                $list = $marks->where('kind', $kind);
                $averages[$kind] = $list->isEmpty() ? null : (int) round($list->avg('score'));
            }

            // Only kinds with marks count, so a course with no homework is not pulled down.
            $used = array_filter($averages, fn ($v) => $v !== null);
            $weightSum = array_sum(array_intersect_key($weights, $used));
            $total = $weightSum > 0 ? (int) round(array_sum(array_map(fn ($kind) => $used[$kind] * $weights[$kind], array_keys($used))) / $weightSum) : null;
            $band = GradeBand::for($total);

            return [
                'course' => $e->course->name.' ('.($e->course->mode === 'zoom' ? 'Zoom' : 'Chat').')',
                'level' => $e->level?->name,
                'teacher' => $e->teacher?->name,
                'averages' => $averages,
                'total' => $total,
                'grade' => $band?->name,
                'comment' => $band?->comment,
                'present' => Attendance::where('enrolment_id', $e->id)->where('status', 'present')
                    ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->count(),
                'marks' => $marks,
            ];
        })->filter(fn (array $row) => $row['marks']->isNotEmpty() || $row['present'] > 0)->values();
    }
}
