<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NetworkActivity extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $type = 'system',
        public ?string $url = null,
        public array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'url' => $this->url,
            'context' => $this->context,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
