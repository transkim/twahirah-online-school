<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The bell: new lessons, homework, marks, classes and announcements. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(30);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications', ['notifications' => $notifications]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('notifications.index'));
    }
}
