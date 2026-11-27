<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Settings the office changes on the System Settings page. Each one replaces a value in config/school.php.
 */
class SchoolSettings
{
    /** @var array<string, array{label: string, help: string, min: int, max: int}> keyed by config path */
    public const FIELDS = [
        'school.grace_days' => ['label' => 'Grace days after a chat plan ends', 'help' => 'Students can still open chat classes for these days.', 'min' => 0, 'max' => 30],
        'school.class_minutes' => ['label' => 'Live class length (minutes)', 'help' => 'Length of each booked Zoom class.', 'min' => 15, 'max' => 240],
        'school.group_size' => ['label' => 'Most students in a small group', 'help' => 'A group closes when it reaches this size.', 'min' => 2, 'max' => 50],
        'school.change_booking_hours' => ['label' => 'Hours before a class to change a booking', 'help' => 'Students can cancel until this many hours before.', 'min' => 0, 'max' => 72],
        'school.reminder_days' => ['label' => 'Days before a plan ends to remind', 'help' => 'Reminder by the app bell and SMS.', 'min' => 0, 'max' => 14],
        'school.mark_weights.exam' => ['label' => 'Exams count in report cards (%)', 'help' => '', 'min' => 0, 'max' => 100],
        'school.mark_weights.homework' => ['label' => 'Homework counts in report cards (%)', 'help' => '', 'min' => 0, 'max' => 100],
        'school.mark_weights.assessment' => ['label' => 'Teacher assessments count in report cards (%)', 'help' => '', 'min' => 0, 'max' => 100],
        'school.ai.daily_questions' => ['label' => 'AI helper questions per student per day', 'help' => '', 'min' => 0, 'max' => 500],
        'school.ai.monthly_tokens' => ['label' => 'AI helper tokens for the school per month', 'help' => 'About 1,000,000 tokens costs USD 4 to 20.', 'min' => 0, 'max' => 1000000000],
    ];

    /** Put the saved values into the running config. Called once per request. */
    public function apply(): void
    {
        try {
            $saved = Cache::rememberForever('school-settings', fn () => Schema::hasTable('settings') ? Setting::pluck('value', 'key')->all() : []);
        } catch (Throwable) {
            return;
        }

        foreach ($saved as $key => $value) {
            if (isset(self::FIELDS[$key])) {
                config([$key => (int) $value]);
            }
        }
    }

    /** @param  array<string, int>  $values  keyed by config path */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (isset(self::FIELDS[$key])) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
                config([$key => (int) $value]);
            }
        }
        Cache::forget('school-settings');
    }
}
