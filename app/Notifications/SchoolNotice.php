<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * An alert in the app's bell: a new lesson, homework, a mark, a booked class or an announcement.
 * Phone alerts through Firebase can be added as a second channel once the school's Firebase project exists.
 */
class SchoolNotice extends Notification
{
    public function __construct(public string $title, public string $body, public ?string $url = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, url: ?string}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
