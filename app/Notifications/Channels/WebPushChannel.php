<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Notifications\Notification;

/**
 * Notification channel "push": web push to all devices of the staff member.
 */
class WebPushChannel
{
    public function __construct(private WebPushService $push) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if (method_exists($notification, 'toWebPush')) {
            $this->push->send($notifiable, $notification->toWebPush($notifiable));
        }
    }
}
