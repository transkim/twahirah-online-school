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
];
