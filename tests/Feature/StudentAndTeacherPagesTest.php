<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassBooking;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\TeacherAvailability;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use App\Services\Zoom\ZoomClient;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentAndTeacherPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
        Carbon::setTestNow(Carbon::parse('2026-12-01 06:00:00', 'UTC')); // Tuesday, 9:00 in Kampala

        $this->app->instance(ZoomClient::class, new class implements ZoomClient
        {
            public function createMeeting(User $teacher, string $topic, Carbon $start, int $minutes): array
            {
                return ['id' => '123', 'join_url' => 'https://zoom.example/j/123'];
            }
        });

        $this->course = Course::where(['name' => 'Quran', 'mode' => 'zoom'])->firstOrFail();
        $this->teacher = User::factory()->teacher()->create();
        TeacherCourse::create(['teacher_id' => $this->teacher->id, 'course_id' => $this->course->id]);
        TeacherAvailability::create(['teacher_id' => $this->teacher->id, 'weekday' => 3, 'start_time' => '10:00', 'end_time' => '12:00']);

        $this->student = User::factory()->create();
        app(EnrolmentService::class)->enrol($this->student, $this->course);
    }

    private function giveZoomPlan(): void
    {
        $plan = Plan::where(['mode' => 'zoom', 'cycle' => 'month', 'days_per_week' => 2])->firstOrFail();
        app(SubscriptionService::class)->grant($this->student, $plan, 'test');
    }

    private function bookWednesdayAtTen(): ClassBooking
    {
        $this->actingAs($this->student)->post('/my/classes', [
            'enrolment_id' => $this->student->enrolments()->first()->id,
            'starts_at' => '2026-12-02T07:00:00+00:00',
            'teacher_id' => $this->teacher->id,
            'kind' => 'one_to_one',
        ])->assertRedirect(route('my.classes'));

        return ClassBooking::firstOrFail();
    }

    public function test_a_student_sees_free_times_and_books_a_class(): void
    {
        $this->giveZoomPlan();

        $this->actingAs($this->student)->get('/my/classes?date=2026-12-02')
            ->assertOk()->assertSee('10:00')->assertSee('11:00')->assertSee($this->teacher->name);

        $booking = $this->bookWednesdayAtTen();
        $this->assertSame($this->teacher->id, $booking->classSession->teacher_id);
        $this->get('/my/classes')->assertSee('Wed 2 Dec, 10:00');
        $this->actingAs($this->teacher)->get('/teach/classes')->assertOk()->assertSee($this->student->name);
    }

    public function test_a_student_without_a_zoom_plan_cannot_book(): void
    {
        $this->actingAs($this->student)->post('/my/classes', [
            'enrolment_id' => $this->student->enrolments()->first()->id,
            'starts_at' => '2026-12-02T07:00:00+00:00',
            'teacher_id' => $this->teacher->id,
            'kind' => 'one_to_one',
        ])->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('class_bookings', 0);
    }

    public function test_a_student_cannot_take_a_seat_in_another_courses_class(): void
    {
        $this->giveZoomPlan();
        $other = ClassSession::create([
            'teacher_id' => $this->teacher->id, 'course_id' => Course::where(['name' => 'Hadith', 'mode' => 'zoom'])->value('id'),
            'kind' => 'group', 'capacity' => 5, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->actingAs($this->student)->post('/my/classes', [
            'enrolment_id' => $this->student->enrolments()->first()->id,
            'starts_at' => $other->starts_at->toIso8601String(),
            'teacher_id' => $this->teacher->id,
            'kind' => 'group',
            'class_session_id' => $other->id,
        ])->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('class_bookings', 0);
    }

    public function test_joining_at_class_time_marks_present_and_opens_zoom(): void
    {
        $this->giveZoomPlan();
        $booking = $this->bookWednesdayAtTen();

        $this->post("/my/classes/{$booking->id}/join")->assertSessionHasErrors('booking');

        Carbon::setTestNow(Carbon::parse('2026-12-02 06:55:00', 'UTC'));
        $this->post("/my/classes/{$booking->id}/join")->assertRedirect('https://zoom.example/j/123');

        $this->assertSame('zoom', Attendance::firstOrFail()->source);
        $this->assertSame(1, $this->student->subscriptions()->first()->sessions_used);
    }

    public function test_a_booking_can_be_cancelled_until_two_hours_before(): void
    {
        $this->giveZoomPlan();
        $booking = $this->bookWednesdayAtTen();

        Carbon::setTestNow(Carbon::parse('2026-12-02 05:30:00', 'UTC'));
        $this->delete("/my/classes/{$booking->id}")->assertSessionHasErrors('booking');

        Carbon::setTestNow(Carbon::parse('2026-12-02 04:00:00', 'UTC'));
        $this->delete("/my/classes/{$booking->id}")->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->refresh()->status);
        $this->assertSame('cancelled', $booking->classSession->status);
    }

    public function test_a_payment_waits_until_the_office_confirms_it(): void
    {
        $plan = Plan::where(['mode' => 'chat', 'cycle' => 'week'])->firstOrFail();

        $this->actingAs($this->student)->post('/my/subscription', ['plan_id' => $plan->id, 'method' => 'mtn', 'phone' => '0700000000']);
        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->get("/my/payments/{$payment->id}")->assertOk()->assertSee($payment->reference);

        $this->actingAs(User::factory()->admin()->create())->get('/admin/payments')->assertOk()->assertSee($payment->reference);
        $this->post("/admin/payments/{$payment->id}/confirm", ['provider_reference' => 'MP123']);

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertTrue(app(SubscriptionService::class)->hasAccess($this->student, 'chat'));
    }

    public function test_a_student_cannot_see_another_students_receipt(): void
    {
        $plan = Plan::where(['mode' => 'chat', 'cycle' => 'week'])->firstOrFail();
        $payment = app(SubscriptionService::class)->startPayment(User::factory()->create(), $plan, 'mtn');

        $this->actingAs($this->student)->get("/my/payments/{$payment->id}")->assertNotFound();
    }

    public function test_anyone_can_change_their_password(): void
    {
        $this->actingAs($this->teacher)->put('/account/password', ['current_password' => 'wrong', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasErrors('current_password');

        $this->put('/account/password', ['current_password' => 'password', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('new-secret-1', $this->teacher->refresh()->password));
    }

    public function test_a_teacher_sets_their_own_free_hours(): void
    {
        $this->actingAs($this->teacher)->post('/teach/availability', ['weekday' => 5, 'start_time' => '14:00', 'end_time' => '16:00'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->teacher->availabilities()->count());
        $this->actingAs($this->student)->post('/teach/availability', ['weekday' => 5, 'start_time' => '14:00', 'end_time' => '16:00'])->assertForbidden();
    }
}
