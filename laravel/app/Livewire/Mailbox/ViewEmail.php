<?php

namespace App\Livewire\Mailbox;

use App\Enums\AuditEvent;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
use App\Services\HtmlSanitizer;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Email')]
#[Layout('layouts.app')]
class ViewEmail extends Component
{
    #[Locked]
    public string $emailId;

    public bool $showExternalImages = false;

    public string $viewMode = 'rendered'; // 'rendered' | 'raw'

    /**
     * @param  InboundEmail  $email  Route-model bound email — authorization + read receipt logged here.
     */
    public function mount(InboundEmail $email, AuditLogger $auditLogger): void
    {
        $this->authorize('view', $email);
        $this->emailId = $email->id;
        $email->markAsRead();
        $auditLogger->log(AuditEvent::EmailRead, $email);
    }

    /**
     * Returns null if the email was deleted while the user was reading it.
     * render() detects the null and redirects back gracefully.
     */
    #[Computed]
    public function email(): ?InboundEmail
    {
        return InboundEmail::with(['alias', 'attachments'])->find($this->emailId);
    }

    /**
     * HTML sanitized via HTMLPurifier — strips all event handlers (onerror, etc.),
     * dangerous tags and CSS. External images blocked by default.
     */
    #[Computed]
    public function safeHtml(): string
    {
        $html = $this->email->body_html ?? '';

        if (empty($html)) {
            return '';
        }

        /** @var HtmlSanitizer $sanitizer */
        $sanitizer = app(HtmlSanitizer::class);

        return $sanitizer->sanitize($html, blockExternalImages: ! $this->showExternalImages);
    }

    #[Computed]
    public function hasBlockedExternalContent(): bool
    {
        $html = $this->email->body_html ?? '';

        if (empty($html)) {
            return false;
        }

        return app(HtmlSanitizer::class)->hasExternalContent($html);
    }

    /** Allow external images for this session — user explicitly opted in. */
    public function allowExternalImages(): void
    {
        $this->showExternalImages = true;
        unset($this->safeHtml);
    }

    /**
     * Switch between rendered and raw HTML view modes.
     *
     * @param  string  $mode  'rendered' | 'raw'
     */
    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['rendered', 'raw'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    /**
     * Redirect back if the email was deleted while the user was reading it
     * (e.g. admin deleted it, or the alias was cleaned up mid-session).
     */
    public function render(): View
    {
        if ($this->email === null) {
            Flux::toast(variant: 'warning', text: __('This email no longer exists.'));
            $this->redirectRoute('mailbox.dashboard', navigate: true);
        }

        return view('livewire.mailbox.view-email');
    }
}
