<?php

namespace App\Livewire\Mailbox;

use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Inbox')]
#[Layout('layouts.app')]
class Inbox extends Component
{
    use WithPagination;

    #[Locked]
    public string $aliasId;

    public string $filter = 'all'; // 'all' | 'unread' | 'read'

    /**
     * Mount and authorize access to the alias.
     */
    public function mount(Alias $alias): void
    {
        $this->authorize('view', $alias);
        $this->aliasId = $alias->id;
    }

    #[Computed]
    public function alias(): Alias
    {
        return Alias::findOrFail($this->aliasId);
    }

    #[Computed]
    public function emails(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = InboundEmail::where('alias_id', $this->aliasId)
            ->latest();

        if ($this->filter === 'unread') {
            $query->unread();
        } elseif ($this->filter === 'read') {
            $query->read();
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return InboundEmail::where('alias_id', $this->aliasId)->unread()->count();
    }

    /**
     * Mark an email as read.
     */
    public function markRead(int $emailId, AuditLogger $auditLogger): void
    {
        $email = InboundEmail::findOrFail($emailId);
        $this->authorize('view', $email);

        $email->markAsRead();

        $auditLogger->log(AuditEvent::EmailRead, $email);
        unset($this->emails, $this->unreadCount);
    }

    /**
     * Mark an email as unread.
     */
    public function markUnread(int $emailId): void
    {
        $email = InboundEmail::findOrFail($emailId);
        $this->authorize('view', $email);
        $email->markAsUnread();
        unset($this->emails, $this->unreadCount);
    }

    /**
     * Delete an email (soft delete).
     */
    public function deleteEmail(int $emailId, AuditLogger $auditLogger): void
    {
        $email = InboundEmail::findOrFail($emailId);
        $this->authorize('delete', $email);

        $auditLogger->log(AuditEvent::EmailDeleted, $email, [
            'subject' => $email->subject,
            'from'    => $email->from_address,
        ]);

        $email->delete();

        unset($this->emails, $this->unreadCount);
        Flux::toast(variant: 'success', text: __('Email deleted.'));
    }

    /**
     * Mark all emails as read.
     */
    public function markAllRead(AuditLogger $auditLogger): void
    {
        InboundEmail::where('alias_id', $this->aliasId)->unread()->update(['read_at' => now()]);
        unset($this->emails, $this->unreadCount);
        Flux::toast(text: __('All emails marked as read.'));
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }
}
