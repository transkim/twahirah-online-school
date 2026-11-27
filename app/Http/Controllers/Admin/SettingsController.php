<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['fields' => SchoolSettings::FIELDS]);
    }

    public function update(Request $request, SchoolSettings $settings): RedirectResponse
    {
        $rules = [];
        foreach (SchoolSettings::FIELDS as $key => $field) {
            $rules[str_replace('.', '__', $key)] = ['required', 'integer', "between:{$field['min']},{$field['max']}"];
        }
        $data = $request->validate($rules);

        $settings->save(collect($data)->mapWithKeys(fn ($v, $k) => [str_replace('__', '.', $k) => (int) $v])->all());

        return back()->with('status', 'Settings saved.');
    }
}
