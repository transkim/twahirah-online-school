<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
    }

    private function chatCourseWithTeacher(): array
    {
        $course = Course::where(['name' => 'Quran', 'mode' => 'chat'])->firstOrFail();
        $teacher = User::factory()->teacher()->create();
        TeacherCourse::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);

        return [$course, $teacher];
    }

    public function test_a_student_signs_in_with_their_phone_and_is_marked_present(): void
    {
        [$course] = $this->chatCourseWithTeacher();
        $student = User::factory()->create(['phone' => '256700111222']);
        app(EnrolmentService::class)->enrol($student, $course);
        app(SubscriptionService::class)->grant($student, Plan::where(['mode' => 'chat', 'cycle' => 'month'])->first(), 'test');

        $this->post('/login', ['login' => '256700111222', 'password' => 'password'])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($student);
        $this->assertSame(1, Attendance::where('student_id', $student->id)->where('source', 'login')->count());
        $this->get('/home')->assertOk()->assertSee('Chat classes · Month');
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->create(['username' => 'amina']);

        $this->post('/login', ['login' => 'amina', 'password' => 'wrong'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_students_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/attendance')->assertForbidden();
    }

    public function test_admin_pages_open(): void
    {
        [$course, $teacher] = $this->chatCourseWithTeacher();
        $student = User::factory()->create();
        app(EnrolmentService::class)->enrol($student, $course);
        $admin = User::factory()->admin()->create();

        foreach (['/admin', '/admin/students', '/admin/students/create', "/admin/students/{$student->id}",
            '/admin/teachers', "/admin/teachers/{$teacher->id}", '/admin/plans', '/admin/attendance'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_adds_a_student_enrols_them_and_records_a_payment(): void
    {
        [$course, $teacher] = $this->chatCourseWithTeacher();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/students', [
            'name' => 'Amina Nakato', 'phone' => '256700333444', 'password' => 'secret123',
        ])->assertRedirect();
        $student = User::where('phone', '256700333444')->firstOrFail();
        $this->assertTrue($student->isStudent());

        $this->actingAs($admin)->post("/admin/students/{$student->id}/enrol", ['course_id' => $course->id])->assertRedirect();
        $this->assertSame($teacher->id, Enrolment::where('student_id', $student->id)->value('teacher_id'));

        $plan = Plan::where(['mode' => 'chat', 'cycle' => 'week'])->firstOrFail();
        $this->actingAs($admin)->post("/admin/students/{$student->id}/payments", ['plan_id' => $plan->id, 'method' => 'cash'])->assertRedirect();

        $sub = Subscription::where('student_id', $student->id)->firstOrFail();
        $this->assertTrue($sub->isUsable());
        $this->assertSame($admin->id, $sub->payment->recorded_by);
        $this->assertSame(10000, $sub->payment->amount_ugx);
    }

    public function test_a_teacher_can_only_mark_their_own_students(): void
    {
        [$course, $teacher] = $this->chatCourseWithTeacher();
        $other = User::factory()->teacher()->create();
        $enrolment = app(EnrolmentService::class)->enrol(User::factory()->create(), $course);
        $today = now(config('school.timezone'))->toDateString();

        $this->actingAs($other)->post("/admin/attendance/{$enrolment->id}", ['date' => $today, 'status' => 'present'])->assertForbidden();
        $this->actingAs($teacher)->post("/admin/attendance/{$enrolment->id}", ['date' => $today, 'status' => 'present'])->assertRedirect();

        $this->assertSame('manual', Attendance::firstOrFail()->source);
    }

    public function test_only_admins_change_prices(): void
    {
        $plan = Plan::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => 'finance']))->put("/admin/plans/{$plan->id}", ['price_ugx' => 1, 'active' => 1])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put("/admin/plans/{$plan->id}", ['price_ugx' => 2500, 'active' => 1])->assertRedirect();

        $this->assertSame(2500, $plan->refresh()->price_ugx);
    }
}
