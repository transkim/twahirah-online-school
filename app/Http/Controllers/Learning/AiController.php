<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\AiTutor;
use App\Services\LearningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** The AI learning helper page for students. */
class AiController extends Controller
{
    public function __construct(private AiTutor $tutor, private LearningService $learning) {}

    public function index(Request $request): View
    {
        $student = $request->user();
        $lesson = $this->lesson($request);
        $hasAccess = $this->learning->scopeFor($student)->isNotEmpty();

        return view('learning.ai', [
            'lesson' => $lesson,
            'messages' => $this->tutor->history($student, $lesson),
            'blocked' => ! $hasAccess ? 'The AI helper opens while you have an active subscription.' : $this->tutor->blockedReason($student),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();
        $data = $request->validate(['question' => ['required', 'string', 'max:2000']]);
        $lesson = $this->lesson($request);

        abort_unless($this->learning->scopeFor($student)->isNotEmpty(), 403, 'The AI helper opens while you have an active subscription.');

        try {
            $this->tutor->ask($student, $data['question'], $lesson);
        } catch (RuntimeException $e) {
            return back()->withErrors(['question' => $e->getMessage()])->withInput();
        }

        return redirect()->to(route('ai.index', array_filter(['lesson' => $lesson?->id])).'#end');
    }

    private function lesson(Request $request): ?Lesson
    {
        $id = $request->integer('lesson');
        if (! $id) {
            return null;
        }

        $lesson = $this->learning->limit(Lesson::whereKey($id), $request->user())->with('course', 'level')->first();
        abort_unless($lesson, 403, 'This lesson is for another course, or your subscription has ended.');

        return $lesson;
    }
}
