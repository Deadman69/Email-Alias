<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Notification bell — shows unread count and a dropdown of recent notifications.
 * Refreshes automatically every 60 seconds via wire:poll.
 */
class NotificationBell extends Component
{
    /**
     * Mark a single notification as read.
     */
    public function markRead(string $notificationId): void
    {
        Auth::user()
            ->notifications()
            ->find($notificationId)
            ?->markAsRead();
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    /**
     * The 20 most recent notifications for the authenticated user.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    #[Computed]
    public function notifications(): \Illuminate\Support\Collection
    {
        return Auth::user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * Count of unread notifications — used for the badge.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.notification-bell');
    }
}
