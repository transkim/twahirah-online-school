<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Mark;
use App\Models\User;
use App\Notifications\SchoolNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Coursera-style online exams: timed, marked automatically where possible, with retakes and a
 * certificate on passing. Written answers wait for the teacher, then the attempt is finished.
 */
class ExamService
{
    /** Minutes allowed after the time runs out, for a slow connection to send the answers. */
    private const GRACE_MINUTES = 2;

    public function __construct(private SubscriptionService $subscriptions) {}

    /** @return Collection<int, Exam> published exams the student can sit now */
    public function availableFor(User $student): Collection
    {
        $enrolments = Enrolment::with('course')->where('student_id', $student->id)->where('status', 'active')->get()
            ->filter(fn (Enrolment $e) => $this->subscriptions->hasAccess($student, $e->course->mode));

        return Exam::with('course', 'level')->where('published', true)
            ->where(function ($q) use ($enrolments) {
                $q->whereRaw('1 = 0');
                foreach ($enrolments as $e) {
                    $q->orWhere(fn ($w) => $w->where('course_id', $e->course_id)
                        ->where(fn ($l) => $l->whereNull('course_level_id')->orWhere('course_level_id', $e->course_level_id)));
                }
            })->orderBy('title')->get();
    }

    /** Start an attempt, or carry on with one that still has time left. */
    public function start(Exam $exam, User $student): ExamAttempt
    {
        if (! $this->availableFor($student)->contains('id', $exam->id)) {
            throw new RuntimeException('This exam is not open to you. Check your course and subscription.');
        }

        return DB::transaction(function () use ($exam, $student) {
            $open = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->where('status', 'in_progress')->lockForUpdate()->first();
            if ($open && $open->due_at->copy()->addMinutes(self::GRACE_MINUTES)->isFuture()) {
                return $open;
            }
            if ($open) {
                $this->submit($open, []);
            }

            $used = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->count();
            if ($exam->max_attempts !== null && $used >= $exam->max_attempts) {
                throw new RuntimeException('You have used all your attempts for this exam.');
            }

            $ids = $exam->questions()->pluck('id')->shuffle();
            if ($ids->isEmpty()) {
                throw new RuntimeException('This exam has no questions yet.');
            }
            if ($exam->questions_per_attempt) {
                $ids = $ids->take($exam->questions_per_attempt);
            }

            return ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'question_ids' => $ids->values()->all(),
                'started_at' => now(),
                'due_at' => now()->addMinutes($exam->minutes),
            ]);
        });
    }

    /**
     * Hand in the answers. Answers sent after the time (and a short grace) are not counted.
     *
     * @param  array<int|string, array{choice?: int|string|null, text?: ?string, attachment_path?: ?string, attachment_mime?: ?string}>  $answers  by question id
     */
    public function submit(ExamAttempt $attempt, array $answers): ExamAttempt
    {
        if ($attempt->status !== 'in_progress') {
            return $attempt;
        }

        $late = now()->gt($attempt->due_at->copy()->addMinutes(self::GRACE_MINUTES));

        return DB::transaction(function () use ($attempt, $answers, $late) {
            $hasWritten = false;
            foreach ($attempt->questions() as $question) {
                $given = $late ? [] : ($answers[$question->id] ?? []);
                $choice = isset($given['choice']) && $given['choice'] !== '' ? (int) $given['choice'] : null;

                if ($question->type === 'choice') {
                    $points = $choice !== null && $choice === $question->correct_option ? $question->points : 0;
                } else {
                    $hasAnswer = trim((string) ($given['text'] ?? '')) !== '' || ! empty($given['attachment_path']);
                    $points = $hasAnswer ? null : 0; // a written answer waits for the teacher
                    $hasWritten = $hasWritten || $hasAnswer;
                }

                $attempt->answers()->updateOrCreate(['exam_question_id' => $question->id], [
                    'choice' => $choice,
                    'text' => $given['text'] ?? null,
                    'attachment_path' => $given['attachment_path'] ?? null,
                    'attachment_mime' => $given['attachment_mime'] ?? null,
                    'points_awarded' => $points,
                ]);
            }

            $attempt->update(['submitted_at' => now(), 'status' => $hasWritten ? 'waiting_for_teacher' : 'marked']);

            return $hasWritten ? $attempt->refresh() : $this->finish($attempt);
        });
    }

    /**
     * The teacher gives points for the written answers.
     *
     * @param  array<int|string, int|string>  $points  by answer id
     */
    public function markWritten(ExamAttempt $attempt, array $points): ExamAttempt
    {
        foreach ($attempt->answers()->with('question')->get() as $answer) {
            if ($answer->question->type === 'written' && array_key_exists($answer->id, $points)) {
                $answer->update(['points_awarded' => max(0, min($answer->question->points, (int) $points[$answer->id]))]);
            }
        }

        if ($attempt->answers()->whereNull('points_awarded')->exists()) {
            return $attempt->refresh();
        }

        $attempt->update(['status' => 'marked']);

        return $this->finish($attempt);
    }

    /** Work out the score, save the student's best mark, and give a certificate on a pass. */
    private function finish(ExamAttempt $attempt): ExamAttempt
    {
        $exam = $attempt->exam;
        $max = $attempt->questions()->sum('points');
        $got = (int) $attempt->answers()->sum('points_awarded');
        $score = $max > 0 ? (int) round($got * 100 / $max) : 0;

        $attempt->update(['points' => $got, 'max_points' => $max, 'score' => $score, 'passed' => $score >= $exam->pass_mark]);

        $best = (int) ExamAttempt::where('exam_id', $exam->id)->where('student_id', $attempt->student_id)->where('status', 'marked')->max('score');
        Mark::updateOrCreate(['student_id' => $attempt->student_id, 'exam_id' => $exam->id], [
            'course_id' => $exam->course_id, 'kind' => 'exam', 'title' => $exam->title, 'score' => $best, 'given_on' => now()->toDateString(),
        ]);

        if ($attempt->passed && $exam->gives_certificate) {
            Certificate::firstOrCreate(['student_id' => $attempt->student_id, 'exam_id' => $exam->id], [
                'course_id' => $exam->course_id,
                'course_level_id' => $exam->course_level_id,
                'code' => strtoupper(Str::random(4).'-'.Str::random(4)),
                'score' => $score,
                'issued_at' => now(),
            ]);
        }

        $attempt->student->notify(new SchoolNotice('Exam result: '.$exam->title, "You scored {$score}%".($attempt->passed ? ' and passed.' : '.'), route('exams.attempt', $attempt)));

        return $attempt->refresh();
    }
}
