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
     * @param  Alias  $alias  Route-model bound alias — authorization checked here.
     */
    public function mount(Alias $alias): void
    {
        $this->authorize('view', $alias);
        $this->aliasId = $alias->id;
    }

    /** @return Alias */
    #[Computed]
    public function alias(): Alias
    {
        return Alias::findOrFail($this->aliasId);
    }

    /** @return \Illuminate\Pagination\LengthAwarePaginator<InboundEmail> */
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

    /**
     * Mark a single email as read and log the event.
     */
    public function markRead(string $emailId, AuditLogger $auditLogger): void
    {
        $email = InboundEmail::findOrFail($emailId);
        $this->authorize('view', $email);
        $email->markAsRead();
        $auditLogger->log(AuditEvent::EmailRead, $email);
        unset($this->emails, $this->unreadCount);
    }

    /**
     * Mark a single email as unread.
     */
    public function markUnread(string $emailId): void
    {
        $email = InboundEmail::findOrFail($emailId);
        $this->authorize('view', $email);
        $email->markAsUnread();
        unset($this->emails, $this->unreadCount);
    }

    /**
     * Permanently delete an email. Only the alias owner can delete.
     */
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

    /**
     * Mark all unread emails as read.
     *
     * Authorization is checked explicitly even though $aliasId is #[Locked],
     * as a defense-in-depth measure against future attribute changes.
     */
    public function markAllRead(AuditLogger $auditLogger): void
    {
        $alias = Alias::findOrFail($this->aliasId);
        $this->authorize('view', $alias);

        InboundEmail::where('alias_id', $this->aliasId)->unread()->update(['read_at' => now()]);
        unset($this->emails, $this->unreadCount);
        Flux::toast(text: __('All emails marked as read.'));
    }

    /** Reset pagination when the filter tab changes. */
    public function updatedFilter(): void
    {
        $this->resetPage();
    }
}
