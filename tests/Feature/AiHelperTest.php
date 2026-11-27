<?php

namespace Tests\Feature;

use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Plan;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiHelperTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
        config(['school.ai.enabled' => true, 'school.ai.api_key' => 'test-key', 'school.ai.daily_questions' => 2]);

        $course = Course::where(['name' => 'Tajweed', 'mode' => 'chat'])->firstOrFail();
        $this->student = User::factory()->create();
        app(EnrolmentService::class)->enrol($this->student, $course);
        app(SubscriptionService::class)->grant($this->student, Plan::where(['mode' => 'chat', 'cycle' => 'month'])->firstOrFail(), 'test');
        $this->lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Qalqalah', 'kind' => 'notes', 'body' => 'The letters of qalqalah are qaf, taa, baa, jeem and daal.']);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'], ['type' => 'text', 'text' => 'There are five letters of qalqalah.']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ])]);
    }

    public function test_a_student_asks_about_a_lesson_and_the_reply_is_kept(): void
    {
        $this->actingAs($this->student)->post("/ai?lesson={$this->lesson->id}", ['question' => 'How many letters?'])->assertSessionHasNoErrors();
        $this->get("/ai?lesson={$this->lesson->id}")->assertSee('There are five letters of qalqalah.');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('x-api-key', 'test-key')
                && $request['model'] === 'claude-opus-5-5'
                && str_contains($request['messages'][0]['content'], 'qaf, taa, baa');
        });

        // The next question sends the earlier reply back exactly as it came.
        $this->post("/ai?lesson={$this->lesson->id}", ['question' => 'Give an example'])->assertSessionHasNoErrors();
        Http::assertSent(fn (Request $request) => count($request['messages']) === 5 && $request['messages'][3]['content'][0]['type'] === 'thinking');
    }

    public function test_the_daily_limit_stops_more_questions(): void
    {
        $this->actingAs($this->student);
        $this->post('/ai', ['question' => 'one']);
        $this->post('/ai', ['question' => 'two']);
        $this->post('/ai', ['question' => 'three'])->assertSessionHasErrors('question');

        $this->assertSame(2, AiMessage::where('role', 'user')->count());
    }

    public function test_it_is_off_without_an_api_key_and_without_a_subscription(): void
    {
        config(['school.ai.enabled' => false]);
        $this->actingAs($this->student)->post('/ai', ['question' => 'hello'])->assertSessionHasErrors('question');

        config(['school.ai.enabled' => true]);
        $this->travel(40)->days();
        $this->post('/ai', ['question' => 'hello'])->assertForbidden();
        Http::assertNothingSent();
    }
}
