<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * Send a text message to a phone number in international form, such as 256752084475.
     */
    public function send(string $phone, string $message): void;
}
