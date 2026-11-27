<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Lesson;
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

class LearningTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);
        Storage::fake('local');

        $this->course = Course::where(['name' => 'Quran', 'mode' => 'chat'])->firstOrFail();
        $this->teacher = User::factory()->teacher()->create();
        TeacherCourse::create(['teacher_id' => $this->teacher->id, 'course_id' => $this->course->id]);
        $this->student = User::factory()->create();
        app(EnrolmentService::class)->enrol($this->student, $this->course);
        app(SubscriptionService::class)->grant($this->student, Plan::where(['mode' => 'chat', 'cycle' => 'month'])->firstOrFail(), 'test');
    }

    public function test_a_teacher_adds_a_lesson_and_the_student_is_told_and_can_listen(): void
    {
        $this->actingAs($this->teacher)->post('/lessons', [
            'course_id' => $this->course->id, 'title' => 'Surah Al-Fatiha', 'kind' => 'audio',
            'file' => UploadedFile::fake()->create('fatiha.mp3', 300, 'audio/mpeg'),
        ])->assertSessionHasNoErrors();
        $lesson = Lesson::firstOrFail();

        $this->assertSame(1, $this->student->unreadNotifications()->count());
        $this->actingAs($this->student)->get('/lessons')->assertSee('Surah Al-Fatiha');
        $this->get("/lessons/{$lesson->id}")->assertOk();
        $this->get("/lessons/{$lesson->id}/file")->assertOk();
    }

    public function test_lessons_lock_without_a_subscription_and_for_other_courses(): void
    {
        $lesson = Lesson::create(['course_id' => $this->course->id, 'title' => 'Lesson one', 'kind' => 'notes']);
        $other = Lesson::create(['course_id' => Course::where(['name' => 'Hadith', 'mode' => 'chat'])->value('id'), 'title' => 'Hadith one', 'kind' => 'notes']);

        $this->actingAs($this->student)->get("/lessons/{$other->id}")->assertForbidden();
        $this->actingAs($this->teacher)->post('/lessons', ['course_id' => $other->course_id, 'title' => 'x', 'kind' => 'notes'])->assertForbidden();

        $this->travel(40)->days();
        $this->actingAs($this->student)->get("/lessons/{$lesson->id}")->assertForbidden();
        $this->get('/lessons')->assertSee('Lessons open while you have an active subscription');
    }

    public function test_homework_is_set_handed_in_and_marked(): void
    {
        $this->actingAs($this->teacher)->post('/homework', ['course_id' => $this->course->id, 'title' => 'Recite Al-Ikhlas'])->assertSessionHasNoErrors();
        $homework = Homework::firstOrFail();

        $this->actingAs($this->student)->post("/homework/{$homework->id}/submit", [
            'file' => UploadedFile::fake()->create('ikhlas.m4a', 100, 'audio/mp4'),
        ])->assertSessionHasNoErrors();
        $submission = HomeworkSubmission::firstOrFail();

        $this->actingAs($this->teacher)->get("/homework/{$homework->id}")->assertSee($this->student->name);
        $this->get("/homework/submissions/{$submission->id}/file")->assertOk();
        $this->post("/homework/submissions/{$submission->id}/mark", ['mark' => 85, 'feedback' => 'Good makharij'])->assertSessionHasNoErrors();

        $this->actingAs($this->student)->get("/homework/{$homework->id}")->assertSee('Mark: 85/100');
        $this->post("/homework/{$homework->id}/submit", ['body' => 'again'])->assertSessionHasErrors('body');
        $this->actingAs(User::factory()->create())->get("/homework/submissions/{$submission->id}/file")->assertForbidden();
    }

    public function test_the_office_sends_an_announcement_to_one_course(): void
    {
        $elsewhere = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->post('/announcements', [
            'title' => 'No class on Friday', 'body' => 'Eid holiday.', 'audience' => 'course', 'course_id' => $this->course->id,
        ])->assertSessionHas('status', 'Announcement sent to 2 people.');

        $this->assertSame(1, $this->student->unreadNotifications()->count());
        $this->assertSame(1, $this->teacher->unreadNotifications()->count());
        $this->assertSame(0, $elsewhere->unreadNotifications()->count());
        $this->actingAs($this->student)->get('/announcements')->assertSee('No class on Friday');
        $this->actingAs($elsewhere)->get('/announcements')->assertDontSee('No class on Friday');
        $this->post('/announcements', ['title' => 'x', 'body' => 'y', 'audience' => 'everyone'])->assertForbidden();
    }
}
