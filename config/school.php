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

    // How much each kind of mark counts in a report card, in percent (to be confirmed by Sheikh).
    'mark_weights' => ['exam' => 60, 'homework' => 20, 'assessment' => 20],

    // The old school portal, read once to bring its students and teachers across.
    'legacy_portal' => [
        'url' => env('LEGACY_PORTAL_URL', 'https://school.twahirahdbms.com'),
        'username' => env('LEGACY_PORTAL_USERNAME'),
        'password' => env('LEGACY_PORTAL_PASSWORD'),
    ],
];
