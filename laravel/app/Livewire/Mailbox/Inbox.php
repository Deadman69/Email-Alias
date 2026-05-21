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
        $query = InboundEmail::where('alias_id', $this->aliasId)->latest();

        match ($this->filter) {
            'unread' => $query->unread(),
            'read'   => $query->read(),
            default  => null,
        };

        return $query->paginate(20);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return InboundEmail::where('alias_id', $this->aliasId)->unread()->count();
    }

    public function markRead(string $emailId, AuditLogger $auditLogger): void
    {
        $email = InboundEmail::findOrFail($emailId);
        $this->authorize('view', $email);
        $email->markAsRead();
        $auditLogger->log(AuditEvent::EmailRead, $email);
        unset($this->emails, $this->unreadCount);
    }

    public function markUnread(string $emailId): void
    {
        $email = InboundEmail::findOrFail($emailId);
        $this->authorize('view', $email);
        $email->markAsUnread();
        unset($this->emails, $this->unreadCount);
    }

    public function deleteEmail(string $emailId, AuditLogger $auditLogger): void
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

    public function markAllRead(AuditLogger $auditLogger): void
    {
        // Explicit authorization belt-and-suspenders — $aliasId is already #[Locked]
        // but we enforce ownership here in case the attribute is ever removed.
        $alias = Alias::findOrFail($this->aliasId);
        $this->authorize('view', $alias);

        InboundEmail::where('alias_id', $this->aliasId)->unread()->update(['read_at' => now()]);
        unset($this->emails, $this->unreadCount);
        Flux::toast(text: __('All emails marked as read.'));
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }
}
