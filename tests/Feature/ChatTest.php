<?php

namespace Tests\Feature;

use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\Course;
use App\Models\Plan;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private ChatGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
        Storage::fake('local');

        $course = Course::where(['name' => 'Quran', 'mode' => 'chat'])->firstOrFail();
        $this->teacher = User::factory()->teacher()->create();
        TeacherCourse::create(['teacher_id' => $this->teacher->id, 'course_id' => $course->id]);
        $this->student = User::factory()->create();
        app(EnrolmentService::class)->enrol($this->student, $course);
        app(SubscriptionService::class)->grant($this->student, Plan::where(['mode' => 'chat', 'cycle' => 'month'])->firstOrFail(), 'test');
        $this->group = ChatGroup::where('kind', 'class')->firstOrFail();
    }

    public function test_a_student_writes_in_their_class_group_and_the_teacher_replies(): void
    {
        $this->actingAs($this->student)->get('/chat')->assertOk()->assertSee($this->group->name);
        $this->post("/chat/{$this->group->id}/messages", ['body' => 'Assalamu alaikum'])->assertSessionHasNoErrors();

        $first = ChatMessage::firstOrFail();
        $this->actingAs($this->teacher)->get("/chat/{$this->group->id}")->assertSee('Assalamu alaikum');
        $this->post("/chat/{$this->group->id}/messages", ['body' => 'Wa alaikum salaam', 'reply_to_id' => $first->id]);

        $this->assertSame($first->id, ChatMessage::latest('id')->first()->reply_to_id);
        $this->actingAs($this->student)->get("/chat/{$this->group->id}")->assertOk()->assertSee('Wa alaikum salaam')->assertSee('✓✓');
    }

    public function test_a_voice_note_is_kept_and_only_group_members_can_play_it(): void
    {
        $this->actingAs($this->student)->post("/chat/{$this->group->id}/messages", [
            'file' => UploadedFile::fake()->create('recitation.mp3', 200, 'audio/mpeg'),
        ])->assertSessionHasNoErrors();

        $message = ChatMessage::firstOrFail();
        $this->assertSame('voice', $message->type);
        $this->actingAs($this->teacher)->get("/chat/messages/{$message->id}/file")->assertOk();
        $this->actingAs(User::factory()->create())->get("/chat/messages/{$message->id}/file")->assertForbidden();
    }

    public function test_the_class_group_locks_when_the_subscription_ends(): void
    {
        $this->travel(40)->days();

        $this->actingAs($this->student)->get("/chat/{$this->group->id}")->assertOk()->assertSee('Your subscription has ended');
        $this->post("/chat/{$this->group->id}/messages", ['body' => 'hello'])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_admins_read_any_group_and_remove_messages_and_mute_members(): void
    {
        $this->actingAs($this->student)->post("/chat/{$this->group->id}/messages", ['body' => 'something rude']);
        $message = ChatMessage::firstOrFail();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get("/chat/{$this->group->id}")->assertOk()->assertSee('something rude');
        $this->delete("/chat/messages/{$message->id}");
        $this->post("/chat/{$this->group->id}/members/{$this->student->id}", ['action' => 'mute']);

        $this->get("/chat/{$this->group->id}")->assertDontSee('something rude')->assertSee('This message was removed');
        $this->actingAs($this->student)->post("/chat/{$this->group->id}/messages", ['body' => 'again'])->assertSessionHasErrors('body');
        $this->delete("/chat/messages/{$message->id}")->assertForbidden();
    }

    public function test_people_outside_a_group_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create())->get("/chat/{$this->group->id}")->assertForbidden();
    }
}
