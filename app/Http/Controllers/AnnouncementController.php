<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use App\Notifications\SchoolNotice;
use App\Services\Sms\SmsSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/** School-wide notices. The office writes them; they reach everyone's bell, and by SMS if asked. */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $staff = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true);

        $announcements = Announcement::with('course', 'author')
            ->when(! $staff, fn ($q) => $q->where(fn ($w) => $w->where('audience', 'everyone')
                ->orWhere('audience', $user->isTeacher() ? 'teachers' : 'students')
                ->orWhere(fn ($c) => $c->where('audience', 'course')->whereIn('course_id', $user->isTeacher()
                    ? $user->teacherCourses()->pluck('course_id')
                    : $user->enrolments()->where('status', 'active')->pluck('course_id')))))
            ->latest()->paginate(20);

        return view('announcements', ['announcements' => $announcements, 'staff' => $staff, 'courses' => $staff ? Course::orderBy('name')->orderBy('mode')->get() : collect()]);
    }

    public function store(Request $request, SmsSender $sms): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', 'in:everyone,students,teachers,course'],
            'course_id' => ['nullable', 'required_if:audience,course', 'exists:courses,id'],
            'sms' => ['nullable', 'boolean'],
        ]);

        $query = User::where('active', true);
        match ($data['audience']) {
            'everyone' => $query->whereIn('role', [User::ROLE_STUDENT, User::ROLE_TEACHER]),
            'students' => $query->where('role', User::ROLE_STUDENT),
            'teachers' => $query->where('role', User::ROLE_TEACHER),
            'course' => $query->where(fn ($w) => $w->whereHas('enrolments', fn ($e) => $e->where('course_id', $data['course_id'])->where('status', 'active'))
                ->orWhereHas('teacherCourses', fn ($t) => $t->where('course_id', $data['course_id']))),
        };
        $people = $query->get();

        $announcement = Announcement::create([
            ...collect($data)->except('sms')->all(),
            'course_id' => $data['audience'] === 'course' ? $data['course_id'] : null,
            'sent_by_sms' => (bool) ($data['sms'] ?? false),
            'recipients' => $people->count(),
            'created_by' => $request->user()->id,
        ]);

        Notification::send($people, new SchoolNotice($announcement->title, $announcement->body, route('announcements.index')));

        if ($announcement->sent_by_sms) {
            foreach ($people->whereNotNull('phone') as $person) {
                $digits = preg_replace('/\D/', '', (string) $person->phone);
                $sms->send(str_starts_with($digits, '0') ? '256'.substr($digits, 1) : $digits, config('school.name').': '.$announcement->title.'. '.$announcement->body);
            }
        }

        return back()->with('status', "Announcement sent to {$people->count()} people.");
    }
}
