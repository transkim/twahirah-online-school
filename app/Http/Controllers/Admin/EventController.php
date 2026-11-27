<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Notifications\SchoolNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/** School events such as graduations, competitions and open days. */
class EventController extends Controller
{
    public function index(): View
    {
        return view('admin.events', [
            'upcoming' => Event::where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->get(),
            'past' => Event::where('starts_at', '<', now()->subDay())->latest('starts_at')->limit(30)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'place' => ['nullable', 'string', 'max:191'],
            'announce' => ['nullable', 'boolean'],
        ]);
        $tz = config('school.timezone');

        $event = Event::create([
            ...collect($data)->except('announce', 'starts_at', 'ends_at')->all(),
            'starts_at' => now()->parse($data['starts_at'], $tz)->utc(),
            'ends_at' => isset($data['ends_at']) ? now()->parse($data['ends_at'], $tz)->utc() : null,
            'announced' => $request->boolean('announce'),
            'created_by' => $request->user()->id,
        ]);

        if ($event->announced) {
            Notification::send(User::where('active', true)->whereIn('role', [User::ROLE_STUDENT, User::ROLE_TEACHER])->get(),
                new SchoolNotice('Event: '.$event->title, $event->starts_at->timezone($tz)->format('l j M, H:i').($event->place ? ' · '.$event->place : ''), route('home')));
        }

        return back()->with('status', 'Event saved'.($event->announced ? ' and announced.' : '.'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return back()->with('status', 'Event removed.');
    }
}
