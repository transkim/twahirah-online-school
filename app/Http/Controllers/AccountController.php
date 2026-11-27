<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Anyone signed in can change their own password. */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is not right.']);
        }

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Your new password is saved.');
    }
}
