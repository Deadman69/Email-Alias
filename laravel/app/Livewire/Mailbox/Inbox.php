<?php

namespace App\Livewire\Mailbox;

use App\Enums\AuditEvent;
use App\Enums\EmailFilter;
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
use Illuminate\View\View;

#[Title('Inbox')]
#[Layout('layouts.app')]
class Inbox extends Component
{
    use WithPagination;

    #[Locked]
    public string $aliasId;

    public string $filter = EmailFilter::All->value;

    public string $search = '';

    // ── Delete email confirmation ─────────────────────────────────────────────────

    public bool $showConfirmDeleteEmail = false;

    #[Locked]
    public string $pendingDeleteEmailId = '';

    /**
     * @param  Alias  $alias  Route-model bound alias — authorization checked here.
     */
    public function mount(Alias $alias): void
    {
        $this->authorize('view', $alias);
        $this->aliasId = $alias->id;
    }

    /**
     * Returns null if the alias was deleted while the user was on this page.
     * render() detects the null and redirects gracefully.
     */
    #[Computed]
    public function alias(): ?Alias
    {
        return Alias::find($this->aliasId);
    }

    /** @return \Illuminate\Pagination\LengthAwarePaginator<InboundEmail> */
    #[Computed]
    public function emails(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = InboundEmail::where('alias_id', $this->aliasId)->latest();

        match ($this->filter) {
            EmailFilter::Unread->value => $query->unread(),
            EmailFilter::Read->value   => $query->read(),
            default                    => null,
        };

        if ($this->search !== '') {
            $query->search($this->search);
        }

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
     * Open the FluxUI confirmation modal before deleting an email.
     */
    public function requestDeleteEmail(string $emailId): void
    {
        $this->pendingDeleteEmailId = $emailId;
        $this->showConfirmDeleteEmail = true;
    }

    /**
     * Permanently delete an email after the user confirmed in the modal.
     * Only the alias owner can delete.
     */
    public function deleteEmail(AuditLogger $auditLogger): void
    {
        if (! $this->pendingDeleteEmailId) {
            return;
        }

        $email = InboundEmail::findOrFail($this->pendingDeleteEmailId);
        $this->authorize('delete', $email);

        $auditLogger->log(AuditEvent::EmailDeleted, $email, [
            'subject' => $email->subject,
            'from'    => $email->from_address,
        ]);

        $email->delete();

        $this->pendingDeleteEmailId = '';
        $this->showConfirmDeleteEmail = false;
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

        $count = InboundEmail::where('alias_id', $this->aliasId)->unread()->count();
        InboundEmail::where('alias_id', $this->aliasId)->unread()->update(['read_at' => now()]);

        if ($count > 0) {
            $auditLogger->log(AuditEvent::EmailsBulkRead, $alias, [
                'alias'   => $alias->address,
                'count'   => $count,
            ]);
        }

        unset($this->emails, $this->unreadCount);
        Flux::toast(variant: 'success', text: __('All emails marked as read.'));
    }

    /** Reset pagination when the filter tab changes. */
    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /** Reset pagination when the search term changes. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Redirect gracefully if the alias was deleted while the user was on this page.
     * This handles both explicit deletion by the owner and background cleanup jobs.
     */
    public function render(): View
    {
        if ($this->alias === null) {
            Flux::toast(variant: 'warning', text: __('This mailbox no longer exists.'));
            $this->redirectRoute('mailbox.dashboard', navigate: true);
        }

        return view('livewire.mailbox.inbox');
    }
}
