<?php

namespace App\Livewire\Intern\Settings;

use App\Enums\NotificationEvent;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Which events notify the user, per channel (bell / mail / push),
 * and the devices that receive web push.
 */
#[Layout('layouts::intern')]
#[Title('Benachrichtigungen')]
class Notifications extends Component
{
    use InteractsWithToasts;

    /** @var array<string, array<string, bool>> */
    public array $settings = [];

    public function mount(): void
    {
        $user = auth()->user();

        foreach (NotificationEvent::cases() as $event) {
            foreach (array_keys(NotificationEvent::CHANNELS) as $channel) {
                $this->settings[$event->value][$channel] = $user->wantsNotification($event, $channel);
            }
        }
    }

    public function save(): void
    {
        $clean = [];
        foreach (NotificationEvent::cases() as $event) {
            foreach (array_keys(NotificationEvent::CHANNELS) as $channel) {
                $clean[$event->value][$channel] = (bool) ($this->settings[$event->value][$channel] ?? false);
            }
        }

        auth()->user()->update(['notification_settings' => $clean]);
        $this->settings = $clean;

        $this->toast('Benachrichtigungen gespeichert.');
    }

    /**
     * @return Collection<int, PushSubscription>
     */
    #[Computed]
    public function devices(): Collection
    {
        return auth()->user()->pushSubscriptions()->latest()->get();
    }

    /**
     * Called by the browser after the user allowed notifications (PushSubscription.toJSON()).
     *
     * @param  array<string, mixed>  $subscription
     */
    public function subscribe(array $subscription, string $userAgent, WebPushService $push): void
    {
        $data = validator($subscription, [
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ])->validate();

        $push->subscribe(auth()->user(), $data, $userAgent);
        unset($this->devices);
        $this->toast('Push-Benachrichtigungen auf diesem Gerät aktiviert.');
    }

    /**
     * The browser turned push off on this device.
     */
    public function unsubscribe(string $endpoint): void
    {
        auth()->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $endpoint))->delete();
        unset($this->devices);
        $this->toast('Push-Benachrichtigungen auf diesem Gerät deaktiviert.');
    }

    public function removeDevice(int $id): void
    {
        auth()->user()->pushSubscriptions()->whereKey($id)->delete();
        unset($this->devices);
        $this->toast('Gerät entfernt.');
    }

    public function sendTest(WebPushService $push): void
    {
        $delivered = $push->send(auth()->user(), [
            'title' => 'Test von '.config('app.name'),
            'body' => 'So sehen Push-Benachrichtigungen aus.',
            'url' => route('intern.settings.notifications'),
            'tag' => 'test',
        ]);

        unset($this->devices);
        $delivered > 0
            ? $this->toast($delivered === 1 ? 'Test an 1 Gerät gesendet.' : "Test an {$delivered} Geräte gesendet.")
            : $this->toast('Kein Gerät erreichbar. Bitte Push auf dem Gerät erneut aktivieren.', 'error');
    }

    public function render()
    {
        return view('livewire.intern.settings.notifications', [
            'events' => NotificationEvent::cases(),
            'channels' => NotificationEvent::CHANNELS,
            'publicKey' => app(WebPushService::class)->publicKey(),
        ]);
    }
}
