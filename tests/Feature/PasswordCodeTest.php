<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordCodeTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone: string, message: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsSender::class, new class($this->sent) implements SmsSender
        {
            /** @param  list<array{phone: string, message: string}>  $sent */
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = ['phone' => $phone, 'message' => $message];
            }
        });
    }

    private function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sent)['message'], $match);

        return $match[1];
    }

    public function test_a_student_gets_a_code_by_sms_and_sets_their_password(): void
    {
        $student = User::factory()->create(['phone' => '0700123456', 'student_number' => 'T/26/0001']);

        $this->post('/password/code', ['login' => '+256 700 123 456'])
            ->assertRedirect(route('password.code.verify'))
            ->assertSessionHas('status', 'We sent a 6-digit code by SMS to 07•• ••• 456.');
        $this->assertSame('256700123456', $this->sent[0]['phone']);

        $this->post('/password/code/verify', ['code' => $this->lastCode(), 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($student);

        $this->post('/logout');
        $this->post('/login', ['login' => 'T/26/0001', 'password' => 'new-secret-1'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_a_code_stops_working_after_five_wrong_tries(): void
    {
        $student = User::factory()->create(['phone' => '0700123456']);
        $this->post('/password/code', ['login' => '0700123456']);
        $code = $this->lastCode();
        $wrong = $code === '111111' ? '222222' : '111111';

        foreach (range(1, 5) as $try) {
            $this->post('/password/code/verify', ['code' => $wrong, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])->assertSessionHasErrors('code');
        }
        $this->post('/password/code/verify', ['code' => $code, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertFalse(password_verify('new-secret-1', $student->refresh()->password));
    }

    public function test_a_shared_phone_asks_for_the_student_number(): void
    {
        User::factory()->create(['phone' => '0700111222', 'student_number' => 'T/26/1']);
        $sister = User::factory()->create(['phone' => '0700111222', 'student_number' => 'T/26/2']);

        $this->post('/password/code', ['login' => '0700111222'])->assertSessionHasErrors('login');
        $this->assertSame([], $this->sent);

        $this->post('/password/code', ['login' => 'T/26/2'])->assertRedirect(route('password.code.verify'));
        $this->post('/password/code/verify', ['code' => $this->lastCode(), 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1']);
        $this->assertAuthenticatedAs($sister);
    }

    public function test_an_unknown_number_gets_the_same_reply_and_no_sms(): void
    {
        $this->post('/password/code', ['login' => '0799999999'])
            ->assertRedirect(route('password.code.verify'))
            ->assertSessionHas('status', 'If that matches an account with a phone number, we sent it a 6-digit code by SMS.');

        $this->assertSame([], $this->sent);
        $this->get('/password/code/verify')->assertOk()->assertSee('Enter your code');
    }

    public function test_people_sharing_a_phone_each_sign_in_with_their_own_password(): void
    {
        User::factory()->create(['phone' => '0700111222', 'password' => 'brother-pass']);
        $sister = User::factory()->create(['phone' => '0700111222', 'password' => 'sister-pass']);

        $this->post('/login', ['login' => '0700111222', 'password' => 'sister-pass'])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($sister);
    }

    public function test_usernames_match_whatever_the_capital_letters(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'akram', 'password' => 'admin-pass-1']);

        $this->post('/login', ['login' => 'Akram', 'password' => 'admin-pass-1'])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}
