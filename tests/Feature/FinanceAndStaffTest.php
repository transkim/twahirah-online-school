<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Course;
use App\Models\FeeDiscount;
use App\Models\LedgerEntry;
use App\Models\LevelChange;
use App\Models\Plan;
use App\Models\Requisition;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceAndStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
        Carbon::setTestNow(Carbon::parse('2026-12-01 06:00:00', 'UTC'));
        $this->admin = User::factory()->admin()->create();
    }

    public function test_a_discount_lowers_the_price_and_a_confirmed_payment_is_posted_to_the_accounts(): void
    {
        $student = User::factory()->create(['student_number' => 'T/26/0042']);
        $plan = Plan::where('active', true)->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/finance/discounts', ['login' => 't/26/0042', 'percent' => 50, 'reason' => 'Orphan support'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, FeeDiscount::count());

        $subscriptions = app(SubscriptionService::class);
        $payment = $subscriptions->startPayment($student, $plan, 'office');
        $this->assertSame((int) (round($plan->price_ugx / 2 / 100) * 100), $payment->amount_ugx);

        $subscriptions->confirmPayment($payment);
        $subscriptions->confirmPayment($payment);

        $entry = LedgerEntry::sole();
        $this->assertSame(Account::byCode('subscriptions')->id, $entry->account_id);
        $this->assertSame($payment->amount_ugx, $entry->amount_ugx);
    }

    public function test_a_requisition_is_approved_then_paid_and_recorded_as_spending(): void
    {
        $teacher = User::factory()->teacher()->create();
        $finance = User::factory()->create(['role' => User::ROLE_FINANCE]);
        $expense = Account::byCode('daawah_expense');

        $this->actingAs($teacher)->post('/requisitions', ['title' => 'Whiteboard markers', 'amount_ugx' => 25000, 'account_id' => $expense->id])
            ->assertSessionHasNoErrors();
        $requisition = Requisition::sole();

        $this->actingAs($finance)->post("/requisitions/{$requisition->id}", ['action' => 'pay'])->assertForbidden();
        $this->actingAs($finance)->post("/requisitions/{$requisition->id}", ['action' => 'approve'])->assertForbidden();

        $this->actingAs($this->admin)->post("/requisitions/{$requisition->id}", ['action' => 'approve'])->assertRedirect();
        $this->actingAs($finance)->post("/requisitions/{$requisition->id}", ['action' => 'pay'])->assertRedirect();

        $this->assertSame('paid', $requisition->fresh()->status);
        $this->assertSame(25000, LedgerEntry::where('account_id', $expense->id)->sum('amount_ugx'));
        $this->assertSame(2, $teacher->unreadNotifications()->count());
        $this->actingAs($this->admin)->get('/admin/finance/daawah')->assertOk()->assertSee('25,000');
    }

    public function test_an_app_sign_up_waits_for_approval_and_gets_the_first_free_student_number(): void
    {
        User::factory()->create(['student_number' => 'T/26/0002']);
        $course = Course::where('active', true)->firstOrFail();

        $this->post('/register', [
            'name' => 'New Learner', 'phone' => '0700 111 222', 'course_id' => $course->id,
            'password' => 'long-password', 'password_confirmation' => 'long-password',
        ])->assertRedirect(route('my.subscribe'));

        $student = User::where('phone', '0700111222')->sole();
        $this->assertTrue($student->self_registered);
        $this->assertSame(1, $student->enrolments()->count());

        $this->actingAs($this->admin)->get('/admin/self-registered')->assertOk()->assertSee('New Learner');
        $this->post("/admin/self-registered/{$student->id}", ['action' => 'approve'])->assertRedirect();

        $this->assertSame('T/26/0001', $student->fresh()->student_number);
        $this->assertNotNull($student->fresh()->approved_at);
        $this->get('/admin/free-numbers')->assertOk()->assertSee('T/26/0003');
    }

    public function test_moving_a_student_to_another_level_is_recorded(): void
    {
        $course = Course::where('active', true)->firstOrFail();
        $first = $course->levels()->create(['name' => 'Level One', 'position' => 1]);
        $second = $course->levels()->create(['name' => 'Level Two', 'position' => 2]);
        $student = User::factory()->create();
        $enrolment = app(EnrolmentService::class)->enrol($student, $course);
        $enrolment->update(['course_level_id' => $first->id]);

        $this->actingAs($this->admin)->post('/admin/student-levels', ['enrolments' => [$enrolment->id], 'course_level_id' => $second->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($second->id, $enrolment->fresh()->course_level_id);
        $change = LevelChange::sole();
        $this->assertSame([$first->id, $second->id], [$change->from_level_id, $change->to_level_id]);
        $this->assertSame(1, $student->unreadNotifications()->count());
    }

    public function test_a_student_rates_their_teacher_and_the_rating_shows_on_appraisals(): void
    {
        $course = Course::where('active', true)->firstOrFail();
        $teacher = User::factory()->teacher()->create(['name' => 'Ustaz Example']);
        $student = User::factory()->create();
        $enrolment = app(EnrolmentService::class)->enrol($student, $course);
        $enrolment->update(['teacher_id' => $teacher->id, 'status' => 'active']);

        $this->actingAs($student)->get('/feedback')->assertOk();
        $this->post('/feedback', ['enrolment_id' => $enrolment->id, 'rating' => 4, 'comment' => 'Very clear'])->assertSessionHasNoErrors();

        $this->actingAs($teacher)->post('/work-plans', ['week_start' => '2026-11-30', 'plan' => 'Surah Al-Mulk, verses 1 to 10'])->assertSessionHasNoErrors();
        $this->get('/work-plans')->assertOk()->assertSee('Surah Al-Mulk');
        $this->get('/feedback')->assertOk()->assertSee('Very clear');

        $this->actingAs($this->admin)->get('/admin/appraisals')->assertOk()->assertSee('Ustaz Example')->assertSee('4.0');
    }

    public function test_the_new_office_pages_open(): void
    {
        $this->actingAs($this->admin);

        foreach (['/admin/finance/accounts', '/admin/finance/statements', '/admin/finance/registration-fees', '/admin/finance/certificate-fees',
            '/admin/finance/discounts', '/requisitions', '/admin/staff/members', '/admin/staff/departments', '/admin/staff/roles',
            '/admin/graduates', '/admin/levels', '/admin/student-levels', '/admin/automatic-messages', '/work-plans'] as $page) {
            $this->get($page)->assertOk();
        }

        $this->actingAs(User::factory()->create())->get('/admin/finance/accounts')->assertForbidden();
    }
}
