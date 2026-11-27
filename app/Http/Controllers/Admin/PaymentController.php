<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Money collected, and payments waiting for the office to confirm them.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $tz = config('school.timezone');
        $from = $request->date('from', 'Y-m-d', $tz) ?? now($tz)->startOfMonth();
        $to = ($request->date('to', 'Y-m-d', $tz) ?? now($tz))->copy()->endOfDay();

        $paid = Payment::where('status', 'paid')->whereBetween('paid_at', [$from->copy()->startOfDay()->utc(), $to->copy()->utc()]);

        return view('admin.payments', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'pending' => Payment::with('student', 'plan')->where('status', 'pending')->latest()->limit(200)->get(),
            'byMethod' => (clone $paid)->selectRaw('method, count(*) as n, sum(amount_ugx) as total')->groupBy('method')->get(),
            'total' => (clone $paid)->sum('amount_ugx'),
            'recent' => (clone $paid)->with('student', 'plan')->latest('paid_at')->limit(100)->get(),
        ]);
    }

    /** The office saw the money arrive: mark the payment paid, which starts the subscription. */
    public function confirm(Request $request, Payment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['provider_reference' => ['nullable', 'string', 'max:100']]);
        $subscriptions->confirmPayment($payment, $data['provider_reference'] ?? null);
        $payment->update(['recorded_by' => $request->user()->id]);

        return back()->with('status', "Payment {$payment->reference} confirmed and the subscription started.");
    }

    public function reject(Payment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->failPayment($payment);

        return back()->with('status', "Payment {$payment->reference} marked as not received.");
    }
}
