<?php

namespace App\Services;

use App\Models\ClassBooking;
use App\Models\ClassSession;
use App\Models\Enrolment;
use App\Models\LeaveRequest;
use App\Models\TeacherAvailability;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\Zoom\ZoomClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Students book their own live Zoom classes. For a chosen day the system shows every time a teacher
 * of their course and level is free: a new one-to-one class, a new small group, or a seat in a group
 * that already has room. The student joins whichever is available.
 */
class BookingService
{
    public function __construct(private SubscriptionService $subscriptions, private ZoomClient $zoom) {}

    /**
     * Options for one day (the date is a day in the school time zone).
     *
     * @return Collection<int, array{starts_at: Carbon, teacher_id: int, kind: string, class_session_id: ?int, seats_left: int}>
     */
    public function options(Enrolment $enrolment, string $date): Collection
    {
        $tz = config('school.timezone');
        $minutes = config('school.class_minutes');
        $day = Carbon::parse($date, $tz)->startOfDay();
        $teacherIds = $this->teachersFor($enrolment);

        $options = collect();

        // Seats in group classes that already exist that day.
        ClassSession::where('course_id', $enrolment->course_id)
            ->where('course_level_id', $enrolment->course_level_id)
            ->where('kind', 'group')
            ->where('status', 'scheduled')
            ->whereBetween('starts_at', [$day->copy()->utc(), $day->copy()->endOfDay()->utc()])
            ->where('starts_at', '>', now())
            ->get()
            ->filter(fn (ClassSession $s) => $s->seatsLeft() > 0)
            ->each(fn (ClassSession $s) => $options->push([
                'starts_at' => $s->starts_at, 'teacher_id' => $s->teacher_id, 'kind' => 'group',
                'class_session_id' => $s->id, 'seats_left' => $s->seatsLeft(),
            ]));

        // New classes in the hours teachers said they are free, where they have nothing booked yet.
        $onLeave = LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $day->toDateString())
            ->whereDate('to_date', '>=', $day->toDateString())->pluck('user_id');
        $windows = TeacherAvailability::whereIn('teacher_id', $teacherIds->diff($onLeave))->where('weekday', $day->dayOfWeek)->get();
        foreach ($windows as $w) {
            $start = $day->copy()->setTimeFromTimeString($w->start_time);
            $end = $day->copy()->setTimeFromTimeString($w->end_time);
            for ($t = $start->copy(); $t->copy()->addMinutes($minutes)->lte($end); $t->addMinutes($minutes)) {
                $slotStart = $t->copy()->utc();
                if ($slotStart->lte(now()) || $this->teacherBusy($w->teacher_id, $slotStart, $minutes)) {
                    continue;
                }
                foreach (['one_to_one' => 1, 'group' => config('school.group_size')] as $kind => $seats) {
                    $options->push([
                        'starts_at' => $slotStart, 'teacher_id' => $w->teacher_id, 'kind' => $kind,
                        'class_session_id' => null, 'seats_left' => $seats,
                    ]);
                }
            }
        }

        return $options->sortBy(fn ($o) => [$o['starts_at']->timestamp, $o['class_session_id'] ? 0 : 1])->values();
    }

    /** Book one of the options returned by options(). */
    public function book(Enrolment $enrolment, array $option): ClassBooking
    {
        $student = $enrolment->student;

        if (! $this->subscriptions->zoomSubscriptionFor($student)) {
            throw new RuntimeException('A Zoom subscription with sessions left is needed to book a class.');
        }

        return DB::transaction(function () use ($enrolment, $option, $student) {
            if ($option['class_session_id']) {
                $session = ClassSession::whereKey($option['class_session_id'])->lockForUpdate()->firstOrFail();
                if ($session->course_id !== $enrolment->course_id || $session->course_level_id !== $enrolment->course_level_id
                    || $session->kind !== 'group' || $session->status !== 'scheduled' || $session->starts_at->lte(now())) {
                    throw new RuntimeException('That class is not open to you. Please choose another time.');
                }
                if ($session->seatsLeft() < 1) {
                    throw new RuntimeException('That class is full. Please choose another time.');
                }
            } else {
                $start = Carbon::parse($option['starts_at'])->utc();
                $minutes = config('school.class_minutes');
                if (! in_array($option['teacher_id'], $this->teachersFor($enrolment)->all(), true)
                    || $this->teacherBusy($option['teacher_id'], $start, $minutes)) {
                    throw new RuntimeException('That time has just been taken. Please choose another time.');
                }
                $teacher = User::findOrFail($option['teacher_id']);
                $meeting = $this->zoom->createMeeting($teacher, $enrolment->course->name, $start, $minutes);
                $session = ClassSession::create([
                    'teacher_id' => $teacher->id,
                    'course_id' => $enrolment->course_id,
                    'course_level_id' => $enrolment->course_level_id,
                    'kind' => $option['kind'],
                    'capacity' => $option['kind'] === 'one_to_one' ? 1 : config('school.group_size'),
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addMinutes($minutes),
                    'zoom_meeting_id' => $meeting['id'] ?? null,
                    'join_url' => $meeting['join_url'] ?? null,
                ]);
            }

            if (ClassBooking::where('class_session_id', $session->id)->where('student_id', $student->id)->where('status', '!=', 'cancelled')->exists()) {
                throw new RuntimeException('You are already booked in this class.');
            }

            return ClassBooking::updateOrCreate(
                ['class_session_id' => $session->id, 'student_id' => $student->id],
                ['status' => 'booked'],
            );
        });
    }

    /**
     * A student cancels a booking. It must be at least the set number of hours before the class.
     * A class nobody is booked in any more is cancelled too, so the teacher's time is free again.
     */
    public function cancel(ClassBooking $booking, ?Carbon $at = null): void
    {
        $at = $at ?? now();
        $session = $booking->classSession;
        $hours = config('school.change_booking_hours');

        if ($booking->status !== 'booked' || $session->starts_at->copy()->subHours($hours)->lte($at)) {
            throw new RuntimeException("Bookings can only be changed up to {$hours} hours before the class.");
        }

        DB::transaction(function () use ($booking, $session) {
            $booking->update(['status' => 'cancelled']);
            if ($session->bookings()->where('status', '!=', 'cancelled')->doesntExist()) {
                $session->update(['status' => 'cancelled']);
            }
        });
    }

    /** @return Collection<int, int> */
    protected function teachersFor(Enrolment $enrolment): Collection
    {
        return TeacherCourse::where('course_id', $enrolment->course_id)
            ->where(fn ($q) => $q->whereNull('course_level_id')->orWhere('course_level_id', $enrolment->course_level_id))
            ->whereHas('teacher', fn ($q) => $q->where('active', true))
            ->pluck('teacher_id')->unique()->values();
    }

    protected function teacherBusy(int $teacherId, Carbon $start, int $minutes): bool
    {
        return ClassSession::where('teacher_id', $teacherId)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $start->copy()->addMinutes($minutes))
            ->where('ends_at', '>', $start)
            ->exists();
    }
}
