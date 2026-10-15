<?php

namespace App\Services;

use App\Models\Mailbox;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\AppSettings;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Sends web push notifications to the browsers/devices of staff members.
 * The VAPID key pair is created on first use and kept in the settings table.
 */
class WebPushService
{
    /**
     * @param  (Closure(array<string, mixed>): WebPush)|null  $factory  replaces the WebPush client (tests)
     */
    public function __construct(private AppSettings $settings, private ?Closure $factory = null) {}

    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /**
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     * @return int number of devices the notification was delivered to
     */
    public function send(User $user, array $payload): int
    {
        $subscriptions = $user->pushSubscriptions()->get()->keyBy('endpoint');

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $client = $this->client();
        $json = json_encode($payload + ['icon' => asset('icons/icon-192.png')]);

        foreach ($subscriptions as $subscription) {
            $client->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $json, ['TTL' => 3600, 'urgency' => 'high']);
        }

        $delivered = 0;
        foreach ($client->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;
            } elseif ($report->isSubscriptionExpired()) {
                // The browser revoked the subscription (uninstalled, permission withdrawn).
                $subscriptions->get($report->getEndpoint())?->delete();
            } else {
                Log::warning('Web push failed: '.$report->getReason(), ['endpoint' => $report->getEndpoint()]);
            }
        }

        return $delivered;
    }

    /**
     * Store a browser subscription (PushSubscription.toJSON() from the browser).
     *
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}}  $data
     */
    public function subscribe(User $user, array $data, ?string $userAgent = null): PushSubscription
    {
        $subscription = PushSubscription::firstOrNew(['endpoint_hash' => hash('sha256', $data['endpoint'])]);
        $subscription->fill([
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
        $subscription->user()->associate($user);
        $subscription->save();

        return $subscription;
    }

    private function client(): WebPush
    {
        $auth = ['VAPID' => [
            'subject' => $this->subject(),
            'publicKey' => $this->keys()['public'],
            'privateKey' => Crypt::decryptString($this->keys()['private']),
        ]];

        return $this->factory
            ? ($this->factory)($auth)
            : new WebPush($auth, logger: $this->logger());
    }

    /**
     * @return array{public: string, private: string} private key encrypted
     */
    private function keys(): array
    {
        if ($keys = $this->settings->get('push_vapid')) {
            return $keys;
        }

        $created = VAPID::createVapidKeys();
        $keys = ['public' => $created['publicKey'], 'private' => Crypt::encryptString($created['privateKey'])];
        $this->settings->set(['push_vapid' => $keys]);

        return $keys;
    }

    /**
     * Contact for push services: the site (https) or the default mailbox.
     */
    private function subject(): string
    {
        $url = (string) config('app.url');

        return str_starts_with($url, 'https://')
            ? $url
            : 'mailto:'.(Mailbox::default()?->email ?? 'servicedesk@example.com');
    }

    /**
     * Warnings go to the Laravel log; the notice about missing GMP/BCMath is skipped.
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger
        {
            public function log($level, string|Stringable $message, array $context = []): void
            {
                if (! in_array($level, [LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG], true)) {
                    Log::log($level, (string) $message, $context);
                }
            }
        };
    }
}
