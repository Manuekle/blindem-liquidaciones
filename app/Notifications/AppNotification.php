<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** A row in the notification panel: what happened, where to go and which icon/tone to show it with. */
abstract class AppNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $icon = 'info',
        public string $tone = 'info',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'icon' => $this->icon, 'tone' => $this->tone];
    }
}
