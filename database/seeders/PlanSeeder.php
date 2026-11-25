<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Prices set by Sheikh Akram on 25 Nov 2026, in UGX. */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Chat classes (the former WhatsApp classes): no session limit.
        $chat = [
            ['day', 1, 2000], ['week', 7, 10000], ['month', 30, 40000], ['year_12m', 365, 420000],
        ];
        foreach ($chat as [$cycle, $days, $price]) {
            Plan::updateOrCreate(
                ['mode' => 'chat', 'days_per_week' => null, 'cycle' => $cycle],
                ['name' => 'Chat classes · '.self::label($cycle), 'duration_days' => $days, 'sessions' => null, 'price_ugx' => $price],
            );
        }

        // Zoom live classes, from "Twahirah Learning App — System Billing Specification".
        $zoom = [
            2 => [['day', 1, 1, 9000], ['week', 7, 2, 17500], ['month', 30, 8, 70000], ['year_12m', 365, 96, 840000], ['year_52w', 365, 104, 910000]],
            3 => [['day', 1, 1, 8500], ['week', 7, 3, 25000], ['month', 30, 12, 100000], ['year_12m', 365, 144, 1200000], ['year_52w', 365, 156, 1300000]],
            5 => [['day', 1, 1, 7500], ['week', 7, 5, 37500], ['month', 30, 20, 150000], ['year_12m', 365, 240, 1800000], ['year_52w', 365, 260, 1950000]],
        ];
        foreach ($zoom as $daysPerWeek => $rows) {
            foreach ($rows as [$cycle, $days, $sessions, $price]) {
                Plan::updateOrCreate(
                    ['mode' => 'zoom', 'days_per_week' => $daysPerWeek, 'cycle' => $cycle],
                    ['name' => "Zoom {$daysPerWeek} days a week · ".self::label($cycle), 'duration_days' => $days, 'sessions' => $sessions, 'price_ugx' => $price],
                );
            }
        }
    }

    private static function label(string $cycle): string
    {
        return ['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year_12m' => 'Year (12 months)', 'year_52w' => 'Year (52 weeks)'][$cycle];
    }
}
