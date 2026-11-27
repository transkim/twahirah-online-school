<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Everyone who works for the center, their departments, and what each role can do. */
class StaffController extends Controller
{
    public function members(): View
    {
        return view('admin.staff.members', [
            'staff' => User::with('department')->whereIn('role', array_keys(UserController::ROLES))->where('active', true)->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function updateMember(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isStudent(), 404);
        $user->update($request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]));

        return back()->with('status', "{$user->name} updated.");
    }

    public function departments(): View
    {
        return view('admin.staff.departments', [
            'departments' => Department::with('head')->withCount('members')->orderBy('name')->get(),
            'staff' => User::whereIn('role', array_keys(UserController::ROLES))->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        Department::create($request->validate(['name' => ['required', 'string', 'max:120'], 'head_id' => ['nullable', 'exists:users,id']]));

        return back()->with('status', 'Department added.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $department->update($request->validate(['name' => ['required', 'string', 'max:120'], 'head_id' => ['nullable', 'exists:users,id']]));

        return back()->with('status', 'Department saved.');
    }

    public function roles(): View
    {
        return view('admin.staff.roles', ['counts' => User::where('active', true)->selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role')]);
    }
}
