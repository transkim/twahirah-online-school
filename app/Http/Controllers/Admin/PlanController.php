<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans', [
            'plans' => Plan::orderBy('mode')->orderBy('days_per_week')->orderBy('duration_days')->orderBy('price_ugx')->get(),
        ]);
    }

    /** Change a price, or stop offering a plan. Existing subscriptions keep what they paid for. */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'price_ugx' => ['required', 'integer', 'min:0'],
            'active' => ['boolean'],
        ]);

        $plan->update(['price_ugx' => $data['price_ugx'], 'active' => $request->boolean('active')]);

        return back()->with('status', 'Plan updated.');
    }
}
