<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\TeacherAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A teacher's own live classes, and the hours they are free for students to book.
 */
class ClassController extends Controller
{
    public function index(Request $request): View
    {
        $teacher = $request->user();

        return view('teacher.classes', [
            'teacher' => $teacher,
            'sessions' => ClassSession::with(['course', 'bookings' => fn ($q) => $q->where('status', '!=', 'cancelled')->with('student')])
                ->where('teacher_id', $teacher->id)->where('status', '!=', 'cancelled')
                ->where('ends_at', '>', now()->subDay())->orderBy('starts_at')->limit(100)->get(),
            'availabilities' => $teacher->availabilities()->orderBy('weekday')->orderBy('start_time')->get(),
        ]);
    }

    public function storeAvailability(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $request->user()->availabilities()->create($data);

        return back()->with('status', 'Free hours added. Students can now book classes in them.');
    }

    public function destroyAvailability(Request $request, TeacherAvailability $availability): RedirectResponse
    {
        abort_unless($availability->teacher_id === $request->user()->id, 404);
        $availability->delete();

        return back()->with('status', 'Free hours removed.');
    }
}
