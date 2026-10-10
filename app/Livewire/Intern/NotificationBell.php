<?php

namespace App\Livewire\Intern;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Bell in the topbar: unread notifications, refreshed by polling (no websockets on Plesk).
 */
class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function latest(): Collection
    {
        return auth()->user()->notifications()->latest()->limit(10)->get();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirectRoute('intern.tickets.show', $notification->data['ticket_id'], navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->unreadCount, $this->latest);
    }

    public function render()
    {
        return view('livewire.intern.notification-bell');
    }
}
