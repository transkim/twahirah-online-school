<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Notifications\SchoolNotice;
use App\Services\LearningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The lesson library: notes, audio, video and class recordings. */
class LessonController extends Controller
{
    public function __construct(private LearningService $learning) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $lessons = $this->learning->limit(Lesson::query(), $user)
            ->with('course', 'level', 'teacher')
            ->when($request->integer('course'), fn ($q, $id) => $q->where('course_id', $id))
            ->latest()->paginate(30)->withQueryString();

        $courses = $user->isStudent() ? collect() : Course::with('levels')->orderBy('name')->orderBy('mode')->get()
            ->filter(fn (Course $c) => $this->learning->canAddTo($user, $c->id));

        return view('learning.lessons', ['lessons' => $lessons, 'courses' => $courses, 'locked' => $user->isStudent() && $this->learning->scopeFor($user)->isEmpty()]);
    }

    public function show(Request $request, Lesson $lesson): View
    {
        $this->authorizeView($request, $lesson);

        return view('learning.lesson', ['lesson' => $lesson->load('course', 'level', 'teacher')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'course_level_id' => ['nullable', 'exists:course_levels,id'],
            'title' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'string', 'max:20000'],
            'kind' => ['required', 'in:notes,audio,video,recording'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'max:102400'],
        ]);
        abort_unless($this->learning->canAddTo($request->user(), (int) $data['course_id']), 403);

        $lesson = Lesson::create([
            ...collect($data)->except('file')->all(),
            'teacher_id' => $request->user()->id,
            ...$this->learning->store($request->file('file'), 'lessons'),
        ]);

        $this->learning->notifyStudents($lesson->course_id, $lesson->course_level_id,
            new SchoolNotice('New lesson: '.$lesson->title, $lesson->course->name, route('lessons.show', $lesson)));

        return redirect()->route('lessons.show', $lesson)->with('status', 'Lesson added. Students on this course were told.');
    }

    public function destroy(Request $request, Lesson $lesson): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $lesson->teacher_id === $request->user()->id, 403);
        $lesson->delete();

        return redirect()->route('lessons.index')->with('status', 'Lesson removed.');
    }

    public function file(Request $request, Lesson $lesson): StreamedResponse
    {
        $this->authorizeView($request, $lesson);
        abort_unless($lesson->attachment_path, 404);

        return Storage::disk('local')->response($lesson->attachment_path, $lesson->attachment_name, ['Content-Type' => $lesson->attachment_mime ?? 'application/octet-stream']);
    }

    private function authorizeView(Request $request, Lesson $lesson): void
    {
        abort_unless($this->learning->limit(Lesson::whereKey($lesson->id), $request->user())->exists(), 403, 'This lesson is for another course, or your subscription has ended.');
    }
}
