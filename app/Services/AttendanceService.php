<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassBooking;
use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance is captured automatically:
 *  - chat classes (the former WhatsApp classes): signing in on a day marks the student present;
 *  - Zoom classes: joining the live class marks them present and uses one session.
 * Teachers can still correct any mark by hand.
 */
class AttendanceService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /** @return Collection<int, Attendance> the marks made (or already there) for today. */
    public function recordSignIn(User $student, ?Carbon $at = null): Collection
    {
        $at = $at ?? now();

        if (! $student->isStudent() || ! $this->subscriptions->hasAccess($student, 'chat', $at)) {
            return collect();
        }

        $date = $at->copy()->setTimezone(config('school.timezone'))->toDateString();

        return Enrolment::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where('mode', 'chat'))
            ->get()
            ->map(fn (Enrolment $e) => Attendance::firstOrCreate(
                ['enrolment_id' => $e->id, 'date' => $date],
                ['student_id' => $student->id, 'status' => 'present', 'source' => 'login', 'marked_at' => $at],
            ));
    }

    /**
     * Called when Zoom reports that the student joined. The first join of a class marks them present
     * and uses one session; joining again after a dropped connection uses nothing more.
     */
    public function recordZoomJoin(ClassBooking $booking, ?Carbon $at = null): ClassBooking
    {
        $at = $at ?? now();

        return DB::transaction(function () use ($booking, $at) {
            $booking = ClassBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $session = $booking->classSession;

            if (! $booking->session_deducted) {
                $sub = $this->subscriptions->useZoomSession($booking->student, $at);
                $booking->forceFill(['session_deducted' => true, 'subscription_id' => $sub->id]);
            }

            $booking->forceFill(['joined_at' => $booking->joined_at ?? $at, 'status' => 'attended'])->save();

            $enrolment = Enrolment::where('student_id', $booking->student_id)->where('course_id', $session->course_id)->first();
            if ($enrolment) {
                Attendance::updateOrCreate(
                    ['enrolment_id' => $enrolment->id, 'date' => $session->starts_at->copy()->setTimezone(config('school.timezone'))->toDateString()],
                    ['student_id' => $booking->student_id, 'status' => 'present', 'source' => 'zoom', 'marked_at' => $at],
                );
            }

            return $booking->refresh();
        });
    }

    /** Called when Zoom reports the student left; adds up the minutes they were in class. */
    public function recordZoomLeave(ClassBooking $booking, ?Carbon $at = null): ClassBooking
    {
        $at = $at ?? now();
        if ($booking->joined_at) {
            $booking->update([
                'left_at' => $at,
                'minutes_attended' => $booking->minutes_attended + (int) $booking->joined_at->diffInMinutes($at),
            ]);
        }

        return $booking->refresh();
    }

    /** A teacher or admin corrects a mark. */
    public function mark(Enrolment $enrolment, string $date, string $status, User $by, ?string $note = null): Attendance
    {
        return Attendance::updateOrCreate(
            ['enrolment_id' => $enrolment->id, 'date' => $date],
            ['student_id' => $enrolment->student_id, 'status' => $status, 'source' => 'manual',
                'marked_at' => now(), 'marked_by' => $by->id, 'note' => $note],
        );
    }
}
