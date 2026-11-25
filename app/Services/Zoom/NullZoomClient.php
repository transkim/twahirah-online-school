<?php

namespace App\Services\Zoom;

use App\Models\User;
use Illuminate\Support\Carbon;

/** Used until the school's Zoom developer account is connected: classes are booked without a meeting link. */
class NullZoomClient implements ZoomClient
{
    public function createMeeting(User $teacher, string $topic, Carbon $start, int $minutes): array
    {
        return ['id' => null, 'join_url' => null];
    }
}
