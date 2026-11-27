<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Who changed what and when. */
class LogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('admin.logs', [
            'logs' => ActivityLog::with('user')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('action', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
                ->latest('id')->paginate(100)->withQueryString(),
            'search' => $search,
        ]);
    }
}
