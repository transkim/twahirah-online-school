<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ChatGroup;
use App\Models\Course;
use App\Models\Plan;
use App\Models\TeacherAvailability;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BookingService;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class SchoolRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
        Carbon::setTestNow(Carbon::parse('2026-12-01 06:00:00', 'UTC')); // a Tuesday, 9:00 in Kampala
    }

    private function plan(string $mode, string $cycle, ?int $days = null): Plan
    {
        return Plan::where(['mode' => $mode, 'cycle' => $cycle, 'days_per_week' => $days])->firstOrFail();
    }

    private function course(string $name, string $mode): Course
    {
        return Course::where(['name' => $name, 'mode' => $mode])->firstOrFail();
    }

    private function teacherFor(Course $course): User
    {
        $t = User::factory()->teacher()->create();
        TeacherCourse::create(['teacher_id' => $t->id, 'course_id' => $course->id]);

        return $t;
    }

    public function test_prices_match_sheikhs_list(): void
    {
        $this->assertSame(2000, $this->plan('chat', 'day')->price_ugx);
        $this->assertSame(420000, $this->plan('chat', 'year_12m')->price_ugx);
        $this->assertSame(70000, $this->plan('zoom', 'month', 2)->price_ugx);
        $this->assertSame(8, $this->plan('zoom', 'month', 2)->sessions);
        $this->assertSame(1950000, $this->plan('zoom', 'year_52w', 5)->price_ugx);
        $this->assertSame(260, $this->plan('zoom', 'year_52w', 5)->sessions);
    }

    public function test_paying_creates_a_subscription_once_and_renewals_add_on(): void
    {
        $svc = app(SubscriptionService::class);
        $student = User::factory()->create();
        $plan = $this->plan('chat', 'week');

        $payment = $svc->startPayment($student, $plan, 'mtn', '256700000000');
        $this->assertFalse($svc->hasAccess($student, 'chat'));

        $sub = $svc->confirmPayment($payment, 'MTN-1');
        $again = $svc->confirmPayment($payment, 'MTN-1');
        $this->assertSame($sub->id, $again->id);
        $this->assertSame(10000, $payment->refresh()->amount_ugx);
        $this->assertTrue($svc->hasAccess($student, 'chat'));
        $this->assertTrue($sub->ends_at->equalTo(now()->addDays(7)));

        $renewal = $svc->confirmPayment($svc->startPayment($student, $plan, 'airtel'));
        $this->assertTrue($renewal->starts_at->equalTo($sub->ends_at));
        $this->assertTrue($renewal->ends_at->equalTo(now()->addDays(14)));
    }

    public function test_chat_access_has_a_short_grace_period_then_locks(): void
    {
        $svc = app(SubscriptionService::class);
        $student = User::factory()->create();
        $svc->grant($student, $this->plan('chat', 'day'), 'test');

        $this->assertTrue($svc->hasAccess($student, 'chat', now()->addDays(2)));
        $this->assertFalse($svc->hasAccess($student, 'chat', now()->addDays(4)));
    }

    public function test_zoom_access_ends_when_sessions_run_out_even_before_the_date(): void
    {
        $svc = app(SubscriptionService::class);
        $student = User::factory()->create();
        $sub = $svc->grant($student, $this->plan('zoom', 'week', 2), 'test');

        $svc->useZoomSession($student);
        $svc->useZoomSession($student);

        $this->assertSame(0, $sub->refresh()->sessionsLeft());
        $this->assertFalse($svc->hasAccess($student, 'zoom'));
        $this->expectException(RuntimeException::class);
        $svc->useZoomSession($student);
    }

    public function test_first_enrolment_picks_the_least_busy_teacher_and_makes_groups(): void
    {
        $course = $this->course('Quran', 'chat');
        $busy = $this->teacherFor($course);
        $free = $this->teacherFor($course);
        $svc = app(EnrolmentService::class);

        $svc->enrol(User::factory()->create(), $course); // goes to $busy (tie, first teacher)
        $student = User::factory()->create();
        $enrolment = $svc->enrol($student, $course);

        $this->assertSame($free->id, $enrolment->teacher_id);
        $group = ChatGroup::where(['kind' => 'class', 'teacher_id' => $free->id])->firstOrFail();
        $this->assertEqualsCanonicalizing([$free->id, $student->id], $group->members->pluck('id')->all());
        $this->assertTrue(ChatGroup::where('kind', 'direct')
            ->whereHas('members', fn ($q) => $q->where('users.id', $student->id))
            ->whereHas('members', fn ($q) => $q->where('users.id', $free->id))->exists());

        // A third student joins the existing group rather than creating a new one.
        $svc->enrol(User::factory()->create(), $course);
        $svc->enrol(User::factory()->create(), $course);
        $this->assertSame(2, ChatGroup::where('kind', 'class')->count());
    }

    public function test_changing_teacher_moves_the_student_between_groups(): void
    {
        $course = $this->course('Tajweed', 'chat');
        $a = $this->teacherFor($course);
        $b = $this->teacherFor($course);
        $svc = app(EnrolmentService::class);
        $student = User::factory()->create();
        $enrolment = $svc->enrol($student, $course);
        $this->assertSame($a->id, $enrolment->teacher_id);

        $svc->changeTeacher($enrolment, $b);

        $this->assertFalse(ChatGroup::where('teacher_id', $a->id)->first()->members->contains($student));
        $this->assertTrue(ChatGroup::where('teacher_id', $b->id)->first()->members->contains($student));
    }

    public function test_signing_in_marks_chat_students_present_once_a_day(): void
    {
        $course = $this->course('Quran', 'chat');
        $this->teacherFor($course);
        $student = User::factory()->create();
        app(EnrolmentService::class)->enrol($student, $course);
        $att = app(AttendanceService::class);

        $this->assertCount(0, $att->recordSignIn($student)); // no subscription yet

        app(SubscriptionService::class)->grant($student, $this->plan('chat', 'month'), 'test');
        $att->recordSignIn($student);
        $att->recordSignIn($student, now()->addHours(3));

        $this->assertSame(1, Attendance::count());
        $this->assertSame('login', Attendance::first()->source);
    }

    public function test_booking_a_zoom_class_and_joining_marks_present_and_uses_one_session(): void
    {
        $course = $this->course('Quran', 'zoom');
        $teacher = $this->teacherFor($course);
        TeacherAvailability::create(['teacher_id' => $teacher->id, 'weekday' => 3, 'start_time' => '10:00', 'end_time' => '12:00']); // Wednesday
        $student = User::factory()->create();
        $enrolment = app(EnrolmentService::class)->enrol($student, $course, null, 2);
        $booking = app(BookingService::class);

        $options = $booking->options($enrolment, '2026-12-02');
        $this->assertCount(4, $options); // 10:00 and 11:00, each as one-to-one or group

        try {
            $booking->book($enrolment, $options[0]);
            $this->fail('Booking without a subscription should be refused.');
        } catch (RuntimeException) {
        }

        $sub = app(SubscriptionService::class)->grant($student, $this->plan('zoom', 'month', 2), 'test');
        $groupOption = $options->firstWhere('kind', 'group');
        $b = $booking->book($enrolment, $groupOption);

        // The group now shows as a seat to join, and the teacher's 10:00 is no longer free for new classes.
        $after = $booking->options($enrolment, '2026-12-02');
        $this->assertSame($b->class_session_id, $after[0]['class_session_id']);
        $this->assertSame(config('school.group_size') - 1, $after[0]['seats_left']);
        $this->assertCount(3, $after);

        $att = app(AttendanceService::class);
        $att->recordZoomJoin($b, Carbon::parse('2026-12-02 07:01', 'UTC'));
        $att->recordZoomJoin($b, Carbon::parse('2026-12-02 07:10', 'UTC')); // rejoined after a drop

        $this->assertSame(1, $sub->refresh()->sessions_used);
        $this->assertSame('zoom', Attendance::firstOrFail()->source);
    }

    public function test_a_full_group_is_no_longer_offered(): void
    {
        config(['school.group_size' => 1]);
        $course = $this->course('Hadith', 'zoom');
        $teacher = $this->teacherFor($course);
        TeacherAvailability::create(['teacher_id' => $teacher->id, 'weekday' => 3, 'start_time' => '10:00', 'end_time' => '11:00']);
        $svc = app(BookingService::class);

        $s1 = User::factory()->create();
        $e1 = app(EnrolmentService::class)->enrol($s1, $course);
        app(SubscriptionService::class)->grant($s1, $this->plan('zoom', 'week', 3), 'test');
        $svc->book($e1, $svc->options($e1, '2026-12-02')->firstWhere('kind', 'group'));

        $e2 = app(EnrolmentService::class)->enrol(User::factory()->create(), $course);
        $this->assertCount(0, $svc->options($e2, '2026-12-02'));
    }
}
