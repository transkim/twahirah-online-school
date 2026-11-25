<?php

namespace App\Services\Zoom;

use App\Models\User;
use Illuminate\Support\Carbon;

interface ZoomClient
{
    /** @return array{id: ?string, join_url: ?string} */
    public function createMeeting(User $teacher, string $topic, Carbon $start, int $minutes): array;
}
