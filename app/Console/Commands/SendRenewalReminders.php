<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SchoolNotice;
use App\Services\Sms\SmsSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('school:renewal-reminders')]
#[Description('Remind students a few days before their subscription ends, by the app bell and SMS')]
class SendRenewalReminders extends Command
{
    public function handle(SmsSender $sms): int
    {
        $days = config('school.reminder_days');
        $sent = 0;

        Subscription::with('student', 'plan')->where('status', 'active')->whereNull('reminded_at')
            ->where('ends_at', '>', now())->where('ends_at', '<=', now()->addDays($days))
            ->each(function (Subscription $sub) use ($sms, &$sent) {
                // A student who has already renewed has a later subscription of the same kind.
                $renewed = Subscription::where('student_id', $sub->student_id)->where('mode', $sub->mode)
                    ->where('status', 'active')->where('starts_at', '>=', $sub->ends_at->copy()->subMinute())->exists();

                if (! $renewed && $sub->student->active) {
                    $when = $sub->ends_at->timezone($sub->student->timezone)->format('j M');
                    $sub->student->notify(new SchoolNotice('Your plan ends on '.$when, 'Renew '.$sub->plan->name.' to keep your classes open.', route('my.subscribe')));
                    if ($sub->student->phone) {
                        $digits = preg_replace('/\D/', '', $sub->student->phone);
                        $sms->send(str_starts_with($digits, '0') ? '256'.substr($digits, 1) : $digits,
                            config('school.name').": your {$sub->plan->name} ends on {$when}. Renew in the app to keep learning.");
                    }
                    $sent++;
                }
                $sub->update(['reminded_at' => now()]);
            });

        $this->info("Sent {$sent} reminders.");

        return self::SUCCESS;
    }
}
