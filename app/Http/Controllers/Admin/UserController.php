<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Office, finance and teacher accounts: who can sign in to the school site and what they can do. */
class UserController extends Controller
{
    public const ROLES = [User::ROLE_ADMIN => 'Administrator', User::ROLE_FINANCE => 'Finance', User::ROLE_TEACHER => 'Teacher'];

    public function index(Request $request): View
    {
        return view('admin.users', [
            'users' => User::whereIn('role', array_keys(self::ROLES))
                ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
                ->orderByDesc('active')->orderBy('role')->orderBy('name')->paginate(50)->withQueryString(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'password' => ['required', Password::min(8)],
        ]);

        User::create($data);

        return back()->with('status', "Account for {$data['name']} made. Give them the username and password.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(array_key_exists($user->role, self::ROLES), 404);
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', Password::min(8)],
        ]);

        if ($user->is($request->user()) && (! $data['active'] || $data['role'] !== User::ROLE_ADMIN)) {
            return back()->withErrors(['role' => 'You cannot switch off or demote your own account.']);
        }

        $user->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('status', "{$user->name} updated.");
    }
}
