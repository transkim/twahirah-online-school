<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\LeaveRequest;
use App\Models\Plan;
use App\Models\TeacherAvailability;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\BookingService;
use App\Services\EnrolmentService;
use App\Services\SchoolSettings;
use App\Services\Sms\SmsSender;
use App\Services\SubscriptionService;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OfficeSectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
        Carbon::setTestNow(Carbon::parse('2026-12-01 06:00:00', 'UTC')); // Tuesday
        $this->admin = User::factory()->admin()->create();
    }

    public function test_approved_leave_shows_on_the_calendar_and_stops_bookings(): void
    {
        $course = Course::where(['name' => 'Quran', 'mode' => 'zoom'])->firstOrFail();
        $teacher = User::factory()->teacher()->create();
        TeacherCourse::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        TeacherAvailability::create(['teacher_id' => $teacher->id, 'weekday' => 3, 'start_time' => '10:00', 'end_time' => '11:00']);
        $student = User::factory()->create();
        $enrolment = app(EnrolmentService::class)->enrol($student, $course);

        $this->actingAs($teacher)->post('/leave', ['from_date' => '2026-12-02', 'to_date' => '2026-12-03', 'reason' => 'Family'])->assertSessionHasNoErrors();
        $this->assertNotEmpty(app(BookingService::class)->options($enrolment, '2026-12-02'));

        $this->actingAs($this->admin)->post('/admin/leave/'.LeaveRequest::firstOrFail()->id, ['status' => 'approved']);

        $this->assertEmpty(app(BookingService::class)->options($enrolment, '2026-12-02'));
        $this->get('/admin/calendar')->assertOk()->assertSee($teacher->name.' on leave');
        $this->assertSame(1, $teacher->unreadNotifications()->count());
    }

    public function test_events_are_saved_and_announced(): void
    {
        $student = User::factory()->create();

        $this->actingAs($this->admin)->post('/admin/events', ['title' => 'Graduation day', 'starts_at' => '2026-12-05T10:00', 'announce' => 1]);

        $this->get('/admin/calendar')->assertSee('Graduation day');
        $this->assertSame(1, $student->unreadNotifications()->count());
    }

    public function test_admins_add_and_switch_off_staff_accounts_and_changes_are_logged(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Office Clerk', 'username' => 'clerk', 'role' => 'finance', 'password' => 'secret-pass-1',
        ])->assertSessionHasNoErrors();
        $clerk = User::where('username', 'clerk')->firstOrFail();

        $this->put("/admin/users/{$clerk->id}", ['role' => 'finance', 'active' => 0]);
        $this->assertFalse($clerk->refresh()->active);

        $this->put("/admin/users/{$this->admin->id}", ['role' => 'finance', 'active' => 1])->assertSessionHasErrors('role');
        $this->assertSame(2, ActivityLog::count());
        $this->get('/admin/logs')->assertOk()->assertSee('Office Clerk');
        $this->assertStringNotContainsString('secret-pass-1', ActivityLog::pluck('subject')->join(' '));

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->get('/admin/users')->assertForbidden();
        $this->get('/admin')->assertDontSee('System Users');
    }

    public function test_settings_change_how_the_school_runs(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk();

        $values = [];
        foreach (SchoolSettings::FIELDS as $key => $field) {
            $values[str_replace('.', '__', $key)] = config($key);
        }
        $values['school__group_size'] = 8;
        $this->put('/admin/settings', $values)->assertSessionHasNoErrors();

        $this->assertSame(8, config('school.group_size'));
        config(['school.group_size' => 5]);
        app(SchoolSettings::class)->apply();
        $this->assertSame(8, config('school.group_size'));
    }

    public function test_students_are_reminded_before_their_plan_ends(): void
    {
        $sent = [];
        $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = $phone;
            }
        });
        $student = User::factory()->create(['phone' => '0700000123']);
        app(SubscriptionService::class)->grant($student, Plan::where(['mode' => 'chat', 'cycle' => 'week'])->firstOrFail(), 'test');

        $this->artisan('school:renewal-reminders')->assertSuccessful();
        $this->assertSame([], $sent);

        $this->travel(5)->days();
        $this->artisan('school:renewal-reminders');
        $this->artisan('school:renewal-reminders');
        $this->assertSame(['256700000123'], $sent);
        $this->assertSame(1, $student->unreadNotifications()->count());
    }

    public function test_the_reports_open(): void
    {
        $student = User::factory()->create(['name' => 'Owing Student']);
        Enrolment::create(['student_id' => $student->id, 'course_id' => Course::first()->id, 'status' => 'active', 'started_on' => '2026-01-01', 'legacy_balance_ugx' => -40000]);

        $this->actingAs($this->admin);
        $this->get('/admin/reports/balances')->assertOk()->assertSee('Owing Student')->assertSee('40,000');
        $this->get('/admin/reports/insights')->assertOk()->assertSee('Owing Student');
        $this->get('/admin/reports/cancelled')->assertOk();
        $this->get('/admin/reports/teachers')->assertOk();
        $this->get("/admin/reports/statement?student={$student->id}")->assertOk()->assertSee('-40,000');
    }
}
