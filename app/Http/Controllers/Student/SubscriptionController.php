<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Payments\PaymentGateway;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Students choose a plan, pay by MTN, Airtel or card, and see their receipts.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user();

        return view('student.subscribe', [
            'plans' => Plan::where('active', true)->orderBy('mode')->orderBy('days_per_week')->orderBy('duration_days')->get()->groupBy(fn (Plan $plan): string => $plan->mode === 'zoom' ? "Zoom live classes, {$plan->days_per_week} days a week" : 'Chat classes'),
            'subscriptions' => $student->subscriptions()->with('plan')->latest('ends_at')->limit(10)->get(),
            'payments' => $student->payments()->with('plan')->latest()->limit(20)->get(),
            'owing' => -min(0, (int) $student->enrolments()->where('legacy_balance_ugx', '<', 0)->sum('legacy_balance_ugx')),
        ]);
    }

    public function store(Request $request, SubscriptionService $subscriptions, PaymentGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'method' => ['required', 'in:mtn,airtel,card'],
            'phone' => ['nullable', 'required_unless:method,card', 'string', 'max:20'],
        ]);

        $payment = $subscriptions->startPayment($request->user(), Plan::findOrFail($data['plan_id']), $data['method'], $data['phone'] ?? null);
        $result = $gateway->request($payment);

        if ($result['redirect_url']) {
            return redirect()->away($result['redirect_url']);
        }

        return redirect()->route('my.payments.show', $payment)->with('status', $result['message']);
    }

    /** A receipt for a paid payment, or the instructions for one still waiting. */
    public function show(Request $request, Payment $payment): View
    {
        abort_unless($payment->student_id === $request->user()->id, 404);

        return view('student.payment', ['payment' => $payment->load('plan', 'student')]);
    }
}
