<?php

return [
    'name' => env('SCHOOL_NAME', 'Twahirah Online School'),

    // School time zone. Teacher availability and class days are kept in this zone;
    // apps show times in each student's own zone.
    'timezone' => env('SCHOOL_TIMEZONE', 'Africa/Kampala'),

    // Days after a chat subscription ends during which the student can still open the app.
    'grace_days' => (int) env('SCHOOL_GRACE_DAYS', 2),

    // Live Zoom classes.
    'class_minutes' => (int) env('SCHOOL_CLASS_MINUTES', 60),
    'group_size' => (int) env('SCHOOL_GROUP_SIZE', 5), // to be confirmed by Sheikh

    // Students can change or cancel a booked live class until this many hours before it starts (to be confirmed by Sheikh).
    'change_booking_hours' => (int) env('SCHOOL_CHANGE_BOOKING_HOURS', 2),

    // Days before a subscription ends when students get a reminder by the app bell and SMS.
    'reminder_days' => (int) env('SCHOOL_REMINDER_DAYS', 3),

    // How much each kind of mark counts in a report card, in percent (to be confirmed by Sheikh).
    'mark_weights' => ['exam' => 60, 'homework' => 20, 'assessment' => 20],

    // The AI learning helper (Claude). It stays off until the school adds its own Anthropic API key.
    'ai' => [
        'enabled' => filled(env('ANTHROPIC_API_KEY')),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('SCHOOL_AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('SCHOOL_AI_EFFORT', 'medium'),
        // Spending limits: tokens for the whole school each month, and questions per student each day.
        'monthly_tokens' => (int) env('SCHOOL_AI_MONTHLY_TOKENS', 20000000),
        'daily_questions' => (int) env('SCHOOL_AI_DAILY_QUESTIONS', 30),
    ],

    // The old school portal, read once to bring its students and teachers across.
    'legacy_portal' => [
        'url' => env('LEGACY_PORTAL_URL', 'https://school.twahirahdbms.com'),
        'username' => env('LEGACY_PORTAL_USERNAME'),
        'password' => env('LEGACY_PORTAL_PASSWORD'),
    ],
];
