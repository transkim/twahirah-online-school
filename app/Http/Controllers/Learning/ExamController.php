<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Services\ExamService;
use App\Services\LearningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    public function __construct(private ExamService $exams, private LearningService $learning) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isStudent()) {
            return view('exams.student', [
                'exams' => $this->exams->availableFor($user),
                'attempts' => ExamAttempt::with('exam')->where('student_id', $user->id)->latest()->get()->groupBy('exam_id'),
                'certificates' => Certificate::with('course', 'level')->where('student_id', $user->id)->latest('issued_at')->get(),
            ]);
        }

        $courses = Course::with('levels')->orderBy('name')->orderBy('mode')->get()->filter(fn (Course $c) => $this->learning->canAddTo($user, $c->id));

        return view('exams.index', [
            'exams' => $this->learning->limit(Exam::query(), $user)->with('course', 'level')->withCount('questions', 'attempts')->latest()->get(),
            'courses' => $courses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        abort_unless($this->learning->canAddTo($request->user(), (int) $data['course_id']), 403);

        $exam = Exam::create([...$data, 'created_by' => $request->user()->id]);

        return redirect()->route('exams.show', $exam)->with('status', 'Exam made. Add its questions, then publish it.');
    }

    public function show(Request $request, Exam $exam): View
    {
        $this->authorizeManage($request, $exam);

        return view('exams.show', [
            'exam' => $exam->load('course', 'level', 'questions'),
            'attempts' => $exam->attempts()->with('student')->latest()->get(),
        ]);
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeManage($request, $exam);
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'between:1,600'],
            'pass_mark' => ['required', 'integer', 'between:0,100'],
            'max_attempts' => ['nullable', 'integer', 'between:1,50'],
            'questions_per_attempt' => ['nullable', 'integer', 'min:1'],
            'published' => ['nullable', 'boolean'],
            'gives_certificate' => ['nullable', 'boolean'],
        ]);
        $exam->update([...$data, 'published' => $request->boolean('published'), 'gives_certificate' => $request->boolean('gives_certificate')]);

        return back()->with('status', $exam->published ? 'Saved. Students can now sit this exam.' : 'Saved. The exam is hidden from students.');
    }

    public function addQuestion(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeManage($request, $exam);
        $data = $request->validate([
            'type' => ['required', 'in:choice,written'],
            'prompt' => ['required', 'string', 'max:5000'],
            'options' => ['nullable', 'required_if:type,choice', 'string', 'max:5000'],
            'correct_option' => ['nullable', 'required_if:type,choice', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'between:1,100'],
        ]);

        $options = null;
        $correct = null;
        if ($data['type'] === 'choice') {
            $options = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $data['options']))));
            if (count($options) < 2 || $data['correct_option'] > count($options)) {
                return back()->withErrors(['options' => 'Give at least two answers, one per line, and the number of the right one.'])->withInput();
            }
            $correct = $data['correct_option'] - 1;
        }

        $exam->questions()->create(['type' => $data['type'], 'prompt' => $data['prompt'], 'options' => $options, 'correct_option' => $correct, 'points' => $data['points']]);

        return back()->with('status', 'Question added.');
    }

    public function removeQuestion(Request $request, Exam $exam, ExamQuestion $question): RedirectResponse
    {
        $this->authorizeManage($request, $exam);
        abort_unless($question->exam_id === $exam->id, 404);
        $question->delete();

        return back()->with('status', 'Question removed.');
    }

    public function start(Request $request, Exam $exam): RedirectResponse
    {
        try {
            $attempt = $this->exams->start($exam, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['exam' => $e->getMessage()]);
        }

        return redirect()->route('exams.attempt', $attempt);
    }

    /** The exam while it is being taken, or the result afterwards. */
    public function attempt(Request $request, ExamAttempt $attempt): View
    {
        $user = $request->user();
        $isOwner = $attempt->student_id === $user->id;
        abort_unless($isOwner || (! $user->isStudent() && ($this->learning->isStaff($user) || $this->learning->canAddTo($user, $attempt->exam->course_id))), 403);

        return view($attempt->status === 'in_progress' && $isOwner ? 'exams.take' : 'exams.result', [
            'attempt' => $attempt->load('exam.course', 'student', 'answers'),
            'questions' => $attempt->questions(),
            'answers' => $attempt->answers->keyBy('exam_question_id'),
            'canMark' => ! $isOwner,
            'certificate' => Certificate::where('student_id', $attempt->student_id)->where('exam_id', $attempt->exam_id)->first(),
        ]);
    }

    public function submit(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->student_id === $request->user()->id, 403);
        $request->validate(['answers' => ['nullable', 'array'], 'files.*' => ['nullable', 'file', 'max:25600']]);

        $answers = [];
        foreach ($attempt->question_ids as $id) {
            $given = $request->input("answers.{$id}", []);
            $file = $request->file("files.{$id}");
            $answers[$id] = [
                'choice' => $given['choice'] ?? null,
                'text' => $given['text'] ?? null,
                'attachment_path' => $file?->store("exams/{$attempt->id}", 'local'),
                'attachment_mime' => $file?->getMimeType(),
            ];
        }

        $this->exams->submit($attempt, $answers);

        return redirect()->route('exams.attempt', $attempt)->with('status', 'Your answers are handed in.');
    }

    public function markWritten(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $user = $request->user();
        abort_unless(! $user->isStudent() && ($user->isAdmin() || $this->learning->canAddTo($user, $attempt->exam->course_id)), 403);
        $data = $request->validate(['points' => ['required', 'array'], 'points.*' => ['integer', 'min:0']]);

        $this->exams->markWritten($attempt, $data['points']);

        return back()->with('status', 'Marks saved.');
    }

    public function answerFile(Request $request, ExamAttempt $attempt, int $answer): StreamedResponse
    {
        $user = $request->user();
        abort_unless($attempt->student_id === $user->id || (! $user->isStudent() && ($this->learning->isStaff($user) || $this->learning->canAddTo($user, $attempt->exam->course_id))), 403);
        $row = $attempt->answers()->whereKey($answer)->whereNotNull('attachment_path')->firstOrFail();

        return Storage::disk('local')->response($row->attachment_path, null, ['Content-Type' => $row->attachment_mime ?? 'application/octet-stream']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'course_level_id' => ['nullable', 'exists:course_levels,id'],
            'title' => ['required', 'string', 'max:191'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'minutes' => ['required', 'integer', 'between:1,600'],
            'pass_mark' => ['required', 'integer', 'between:0,100'],
            'max_attempts' => ['nullable', 'integer', 'between:1,50'],
        ]);
    }

    private function authorizeManage(Request $request, Exam $exam): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isTeacher() && $this->learning->canAddTo($user, $exam->course_id)), 403);
    }
}
