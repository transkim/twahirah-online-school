<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FeeDiscount;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionService
{
    /** Start a payment for a plan. It becomes a subscription once the payment is confirmed. */
    public function startPayment(User $student, Plan $plan, string $method, ?string $phone = null): Payment
    {
        if (! $plan->active) {
            throw new RuntimeException('This plan is no longer offered.');
        }

        return Payment::create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'amount_ugx' => $this->priceFor($student, $plan),
            'method' => $method,
            'phone' => $phone,
            'reference' => 'TOS-'.strtoupper(Str::random(10)),
            'status' => 'pending',
        ]);
    }

    /**
     * The plan price after the student's discount, if they have one. Rounded to whole hundreds
     * so Mobile Money payments go through cleanly.
     */
    public function priceFor(User $student, Plan $plan): int
    {
        $percent = (int) FeeDiscount::where('student_id', $student->id)->current()->max('percent');

        return $percent > 0 ? (int) (round($plan->price_ugx * (100 - min(100, $percent)) / 100 / 100) * 100) : $plan->price_ugx;
    }

    /** Called when MTN, Airtel or the card provider confirms the money arrived. Safe to call twice. */
    public function confirmPayment(Payment $payment, ?string $providerReference = null, ?Carbon $at = null): Subscription
    {
        return DB::transaction(function () use ($payment, $providerReference, $at) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid') {
                return Subscription::where('payment_id', $payment->id)->firstOrFail();
            }

            $payment->update([
                'status' => 'paid',
                'paid_at' => $at ?? now(),
                'provider_reference' => $providerReference,
            ]);

            if ($account = Account::where('code', 'subscriptions')->first()) {
                LedgerEntry::create([
                    'account_id' => $account->id, 'amount_ugx' => $payment->amount_ugx, 'payment_id' => $payment->id,
                    'student_id' => $payment->student_id, 'description' => 'Subscription: '.$payment->plan?->name,
                    'entry_date' => ($at ?? now())->copy()->setTimezone(config('school.timezone'))->toDateString(),
                ]);
            }

            return $this->activate($payment->student, $payment->plan, $payment, null, $at);
        });
    }

    public function failPayment(Payment $payment): void
    {
        if ($payment->status === 'pending') {
            $payment->update(['status' => 'failed']);
        }
    }

    /** Give a subscription without payment, for example a scholarship. */
    public function grant(User $student, Plan $plan, string $note, ?Carbon $at = null): Subscription
    {
        return $this->activate($student, $plan, null, $note, $at);
    }

    /**
     * Create the subscription. A renewal bought before the current one ends starts when it ends,
     * so the student never loses paid days.
     */
    protected function activate(User $student, Plan $plan, ?Payment $payment, ?string $note, ?Carbon $at): Subscription
    {
        $now = $at ?? now();

        $sameKind = Subscription::where('student_id', $student->id)
            ->where('mode', $plan->mode)
            ->where('status', 'active')
            ->where('ends_at', '>', $now)
            ->when($plan->mode === 'zoom', fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('days_per_week', $plan->days_per_week)))
            ->max('ends_at');

        $start = $sameKind ? Carbon::parse($sameKind)->max($now) : $now->copy();

        return Subscription::create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'payment_id' => $payment?->id,
            'mode' => $plan->mode,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays($plan->duration_days),
            'sessions_total' => $plan->sessions,
            'sessions_used' => 0,
            'status' => 'active',
            'note' => $note,
        ]);
    }

    /** Can the student use this kind of class right now? Chat classes allow a short grace period. */
    public function hasAccess(User $student, string $mode, ?Carbon $at = null): bool
    {
        $at = $at ?? now();

        if (Subscription::where('student_id', $student->id)->where('mode', $mode)->usable($at)->exists()) {
            return true;
        }

        if ($mode === 'chat' && ($grace = config('school.grace_days')) > 0) {
            return Subscription::where('student_id', $student->id)
                ->where('mode', 'chat')
                ->where('status', 'active')
                ->where('starts_at', '<=', $at)
                ->where('ends_at', '>', $at->copy()->subDays($grace))
                ->exists();
        }

        return false;
    }

    /** The Zoom subscription that the next live class will use: the one that ends first. */
    public function zoomSubscriptionFor(User $student, ?Carbon $at = null): ?Subscription
    {
        return Subscription::where('student_id', $student->id)
            ->where('mode', 'zoom')
            ->usable($at ?? now())
            ->orderBy('ends_at')
            ->first();
    }

    /** Use one Zoom session. Throws when the student has none left. */
    public function useZoomSession(User $student, ?Carbon $at = null): Subscription
    {
        return DB::transaction(function () use ($student, $at) {
            $sub = $this->zoomSubscriptionFor($student, $at);

            if (! $sub) {
                throw new RuntimeException('No active Zoom subscription with sessions left.');
            }

            $sub = Subscription::whereKey($sub->id)->lockForUpdate()->first();
            $sub->increment('sessions_used');

            return $sub->refresh();
        });
    }

    /** Mark subscriptions whose dates or sessions are used up as ended. Run daily. */
    public function endExpired(?Carbon $at = null): int
    {
        $at = $at ?? now();

        return Subscription::where('status', 'active')
            ->where(fn ($q) => $q->where('ends_at', '<=', $at)
                ->orWhere(fn ($w) => $w->whereNotNull('sessions_total')->whereColumn('sessions_used', '>=', 'sessions_total')))
            ->update(['status' => 'ended']);
    }
}
