<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BroadcastAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $messageBody,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? $notifiable->username ?? __('there');

        return (new MailMessage)
            ->subject($this->title)
            ->greeting(__('Hello :name,', ['name' => $name]))
            ->line($this->messageBody)
            ->line(__('This message was sent from :app.', ['app' => config('app.name')]));
    }
}
