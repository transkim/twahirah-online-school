<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Notifications\SchoolNotice;
use App\Services\LearningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Teachers set homework; students hand it in (text, a voice recitation or a file); teachers mark it. */
class HomeworkController extends Controller
{
    public function __construct(private LearningService $learning) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $homework = $this->learning->limit(Homework::query(), $user)
            ->with(['course', 'level', 'teacher', 'submissions' => fn ($q) => $user->isStudent() ? $q->where('student_id', $user->id) : $q])
            ->withCount('submissions')
            ->latest()->paginate(30);

        $courses = $user->isStudent() ? collect() : Course::with('levels')->orderBy('name')->orderBy('mode')->get()
            ->filter(fn (Course $c) => $this->learning->canAddTo($user, $c->id));

        return view('learning.homework', ['homework' => $homework, 'courses' => $courses]);
    }

    public function show(Request $request, Homework $homework): View
    {
        $user = $request->user();
        $this->authorizeView($request, $homework);

        return view('learning.homework-show', [
            'homework' => $homework->load('course', 'level', 'teacher', 'lesson'),
            'mine' => $user->isStudent() ? $homework->submissions()->where('student_id', $user->id)->first() : null,
            'submissions' => $user->isStudent() ? collect() : $homework->submissions()->with('student')->orderBy('submitted_at')->get(),
            'canMark' => $user->isAdmin() || $homework->teacher_id === $user->id || ($user->isTeacher() && $this->learning->canAddTo($user, $homework->course_id)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'course_level_id' => ['nullable', 'exists:course_levels,id'],
            'title' => ['required', 'string', 'max:191'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'due_at' => ['nullable', 'date'],
        ]);
        abort_unless($this->learning->canAddTo($request->user(), (int) $data['course_id']), 403);

        $homework = Homework::create([...$data, 'teacher_id' => $request->user()->id]);
        $this->learning->notifyStudents($homework->course_id, $homework->course_level_id,
            new SchoolNotice('New homework: '.$homework->title, $homework->due_at ? 'Due '.$homework->due_at->format('j M') : $homework->course->name, route('homework.show', $homework)));

        return redirect()->route('homework.show', $homework)->with('status', 'Homework set. Students on this course were told.');
    }

    public function submit(Request $request, Homework $homework): RedirectResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $this->authorizeView($request, $homework);
        $data = $request->validate([
            'body' => ['nullable', 'required_without:file', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);

        $existing = HomeworkSubmission::where('homework_id', $homework->id)->where('student_id', $request->user()->id)->first();
        if ($existing?->mark !== null) {
            return back()->withErrors(['body' => 'This homework is already marked.']);
        }

        HomeworkSubmission::updateOrCreate(
            ['homework_id' => $homework->id, 'student_id' => $request->user()->id],
            ['body' => $data['body'] ?? null, 'submitted_at' => now(), ...$this->learning->store($request->file('file'), "homework/{$homework->id}")],
        );

        return back()->with('status', 'Handed in. Your teacher will mark it.');
    }

    public function mark(Request $request, HomeworkSubmission $submission): RedirectResponse
    {
        $user = $request->user();
        $homework = $submission->homework;
        abort_unless($user->isAdmin() || $homework->teacher_id === $user->id || ($user->isTeacher() && $this->learning->canAddTo($user, $homework->course_id)), 403);

        $data = $request->validate(['mark' => ['required', 'integer', 'between:0,100'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $submission->update([...$data, 'marked_by' => $user->id, 'marked_at' => now()]);
        $submission->student->notify(new SchoolNotice('Homework marked: '.$homework->title, "Mark {$data['mark']} out of 100", route('homework.show', $homework)));

        return back()->with('status', 'Mark saved and the student was told.');
    }

    public function file(Request $request, HomeworkSubmission $submission): StreamedResponse
    {
        $user = $request->user();
        abort_unless($submission->attachment_path, 404);
        abort_unless($submission->student_id === $user->id || ! $user->isStudent() && ($this->learning->isStaff($user) || $this->learning->canAddTo($user, $submission->homework->course_id)), 403);

        return Storage::disk('local')->response($submission->attachment_path, $submission->attachment_name, ['Content-Type' => $submission->attachment_mime ?? 'application/octet-stream']);
    }

    private function authorizeView(Request $request, Homework $homework): void
    {
        abort_unless($this->learning->limit(Homework::whereKey($homework->id), $request->user())->exists(), 403, 'This homework is for another course, or your subscription has ended.');
    }
}
