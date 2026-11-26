<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Used until the school's SMS account is connected: messages are written to the log instead of sent. */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS not sent (no SMS account connected yet)', ['to' => $phone, 'message' => $message]);
    }
}
