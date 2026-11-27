<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Mark;
use App\Models\Plan;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Database\Seeders\CourseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamsAndResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, CourseSeeder::class]);

        $this->course = Course::where(['name' => 'Tajweed', 'mode' => 'chat'])->firstOrFail();
        $this->teacher = User::factory()->teacher()->create();
        TeacherCourse::create(['teacher_id' => $this->teacher->id, 'course_id' => $this->course->id]);
        $this->student = User::factory()->create();
        app(EnrolmentService::class)->enrol($this->student, $this->course);
        app(SubscriptionService::class)->grant($this->student, Plan::where(['mode' => 'chat', 'cycle' => 'month'])->firstOrFail(), 'test');
    }

    private function makeExam(bool $written = false, ?int $attempts = null): Exam
    {
        $this->actingAs($this->teacher)->post('/exams', [
            'course_id' => $this->course->id, 'title' => 'Tajweed final', 'minutes' => 20, 'pass_mark' => 50, 'max_attempts' => $attempts,
        ]);
        $exam = Exam::firstOrFail();
        $this->post("/exams/{$exam->id}/questions", ['type' => 'choice', 'prompt' => 'How many letters of qalqalah?', 'options' => "3\n5\n7", 'correct_option' => 2, 'points' => 1])->assertSessionHasNoErrors();
        if ($written) {
            $this->post("/exams/{$exam->id}/questions", ['type' => 'written', 'prompt' => 'Explain idghaam.', 'points' => 3]);
        }
        $this->put("/exams/{$exam->id}", ['minutes' => 20, 'pass_mark' => 50, 'max_attempts' => $attempts, 'published' => 1, 'gives_certificate' => 1]);

        return $exam->refresh();
    }

    private function answer(Exam $exam, int $choice, ?string $text = null): ExamAttempt
    {
        $this->actingAs($this->student)->post("/exams/{$exam->id}/start");
        $attempt = ExamAttempt::latest('id')->firstOrFail();
        $answers = [];
        foreach ($exam->questions as $q) {
            $answers[$q->id] = $q->type === 'choice' ? ['choice' => $choice] : ['text' => $text];
        }
        $this->post("/exam-attempts/{$attempt->id}", ['answers' => $answers]);

        return $attempt->refresh();
    }

    public function test_a_multiple_choice_exam_is_marked_at_once_and_a_pass_gives_a_certificate(): void
    {
        $exam = $this->makeExam();
        $this->actingAs($this->student)->get('/exams')->assertSee('Tajweed final');

        $attempt = $this->answer($exam, 1);

        $this->assertSame(100, $attempt->score);
        $this->assertTrue($attempt->passed);
        $certificate = Certificate::firstOrFail();
        $this->get("/certificates/{$certificate->id}")->assertOk()->assertSee($this->student->name);
        $this->post('/logout');
        $this->get("/certificates/check/{$certificate->code}")->assertOk()->assertSee('This certificate is real');
    }

    public function test_written_answers_wait_for_the_teacher_then_count(): void
    {
        $exam = $this->makeExam(written: true);
        $attempt = $this->answer($exam, 0, 'Merging a letter into the next.');
        $this->assertSame('waiting_for_teacher', $attempt->status);

        $written = $attempt->answers()->whereNotNull('text')->firstOrFail();
        $this->actingAs($this->teacher)->post("/exam-attempts/{$attempt->id}/mark", ['points' => [$written->id => 3]]);

        $attempt->refresh();
        $this->assertSame('marked', $attempt->status);
        $this->assertSame(75, $attempt->score);
        $this->assertSame(75, Mark::where('kind', 'exam')->value('score'));
    }

    public function test_retakes_stop_at_the_limit_and_the_best_score_is_kept(): void
    {
        $exam = $this->makeExam(attempts: 2);
        $this->answer($exam, 1);
        $this->answer($exam, 0);

        $this->actingAs($this->student)->post("/exams/{$exam->id}/start")->assertSessionHasErrors('exam');
        $this->assertSame(100, Mark::where('kind', 'exam')->value('score'));
    }

    public function test_answers_sent_after_the_time_do_not_count(): void
    {
        $exam = $this->makeExam();
        $this->actingAs($this->student)->post("/exams/{$exam->id}/start");
        $attempt = ExamAttempt::firstOrFail();

        $this->travel(30)->minutes();
        $this->post("/exam-attempts/{$attempt->id}", ['answers' => [$exam->questions->first()->id => ['choice' => 1]]]);

        $this->assertSame(0, $attempt->refresh()->score);
    }

    public function test_a_student_without_a_subscription_cannot_start(): void
    {
        $exam = $this->makeExam();
        $this->travel(40)->days();

        $this->actingAs($this->student)->post("/exams/{$exam->id}/start")->assertSessionHasErrors('exam');
    }

    public function test_the_report_card_weights_marks_and_shows_the_grade(): void
    {
        Mark::create(['student_id' => $this->student->id, 'course_id' => $this->course->id, 'kind' => 'exam', 'title' => 'Final', 'score' => 90, 'given_on' => now()->toDateString()]);
        $this->actingAs($this->teacher)->post('/marks', [
            'course_id' => $this->course->id, 'title' => 'Monthly test', 'given_on' => now()->toDateString(), 'scores' => [$this->student->id => 60],
        ])->assertSessionHas('status', 'Saved 1 marks.');

        // Exam 60% and assessment 20%: (90*60 + 60*20) / 80 = 82.5, so 83 and an A.
        $this->actingAs($this->student)->get('/results')->assertOk()->assertSee('83')->assertSee('Excellent');
        $this->actingAs(User::factory()->create())->get("/students/{$this->student->id}/report-card")->assertForbidden();
    }

    public function test_admins_change_the_grade_bands(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put('/admin/grading', ['bands' => [
            ['name' => 'Mumtaz', 'min_score' => 85, 'comment' => 'Excellent'], ['name' => 'Rasib', 'min_score' => 0, 'comment' => ''],
        ]])->assertSessionHasNoErrors();

        $this->get('/admin/grading')->assertSee('Mumtaz');
    }
}
