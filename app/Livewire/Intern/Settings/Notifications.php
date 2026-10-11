<?php

namespace App\Livewire\Intern\Settings;

use App\Enums\NotificationEvent;
use App\Livewire\Concerns\InteractsWithToasts;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Which events notify the user, per channel (bell / mail).
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

    public function render()
    {
        return view('livewire.intern.settings.notifications', [
            'events' => NotificationEvent::cases(),
            'channels' => NotificationEvent::CHANNELS,
        ]);
    }
}
