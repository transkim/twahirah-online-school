<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassBooking;
use App\Models\Enrolment;
use App\Models\User;
use App\Notifications\SchoolNotice;
use App\Services\AttendanceService;
use App\Services\BookingService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

/**
 * A student's live Zoom classes: book a time, see what is booked, join, or cancel.
 */
class ClassController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request, SubscriptionService $subscriptions): View
    {
        $student = $request->user();
        $enrolments = $student->enrolments()->with('course', 'level')->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where('mode', 'zoom'))->get();

        $enrolment = $enrolments->firstWhere('id', (int) $request->query('enrolment')) ?? $enrolments->first();
        $date = $request->query('date', now(config('school.timezone'))->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now(config('school.timezone'))->toDateString();
        }

        $options = $enrolment ? $this->bookings->options($enrolment, $date) : collect();

        return view('student.classes', [
            'student' => $student,
            'enrolments' => $enrolments,
            'enrolment' => $enrolment,
            'date' => $date,
            'options' => $options,
            'teachers' => User::whereIn('id', $options->pluck('teacher_id')->unique())->pluck('name', 'id'),
            'subscription' => $subscriptions->zoomSubscriptionFor($student),
            'booked' => ClassBooking::with('classSession.course', 'classSession.teacher')
                ->where('student_id', $student->id)->where('status', '!=', 'cancelled')
                ->whereHas('classSession', fn ($q) => $q->where('ends_at', '>', now()))
                ->get()->sortBy('classSession.starts_at'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enrolment_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'teacher_id' => ['required', 'integer'],
            'kind' => ['required', 'in:one_to_one,group'],
            'class_session_id' => ['nullable', 'integer'],
        ]);

        $enrolment = Enrolment::where('student_id', $request->user()->id)->where('status', 'active')->findOrFail($data['enrolment_id']);

        try {
            $booking = $this->bookings->book($enrolment, [
                'starts_at' => Carbon::parse($data['starts_at']),
                'teacher_id' => (int) $data['teacher_id'],
                'kind' => $data['kind'],
                'class_session_id' => empty($data['class_session_id']) ? null : (int) $data['class_session_id'],
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        $session = $booking->classSession;
        $session->teacher->notify(new SchoolNotice('New class booking', $request->user()->name.' booked '.$session->course->name.' on '
            .$session->starts_at->timezone($session->teacher->timezone)->format('D j M, H:i'), route('teach.classes')));

        return redirect()->route('my.classes')->with('status', 'Your class is booked. You can join it here at class time.');
    }

    public function destroy(Request $request, ClassBooking $booking): RedirectResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 404);

        try {
            $this->bookings->cancel($booking);
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', 'Your booking is cancelled.');
    }

    /** Open the Zoom class. Joining marks the student present and uses one Zoom session. */
    public function join(Request $request, ClassBooking $booking, AttendanceService $attendance): RedirectResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 404);
        $session = $booking->classSession;

        if ($booking->status === 'cancelled' || now()->lt($session->starts_at->copy()->subMinutes(10)) || now()->gte($session->ends_at)) {
            return back()->withErrors(['booking' => 'You can join from 10 minutes before the class until it ends.']);
        }
        if (! $session->join_url) {
            return back()->withErrors(['booking' => 'The Zoom link for this class is not ready yet. Please contact the office.']);
        }

        try {
            $attendance->recordZoomJoin($booking);
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->away($session->join_url);
    }
}
