<?php

namespace App\Livewire\Mailbox;

use App\Enums\AuditEvent;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
use App\Services\HtmlSanitizer;
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

    public function mount(InboundEmail $email, AuditLogger $auditLogger): void
    {
        $this->authorize('view', $email);
        $this->emailId = $email->id;
        $email->markAsRead();
        $auditLogger->log(AuditEvent::EmailRead, $email);
    }

    #[Computed]
    public function email(): InboundEmail
    {
        return InboundEmail::with(['alias', 'attachments'])->findOrFail($this->emailId);
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

    public function allowExternalImages(): void
    {
        $this->showExternalImages = true;
        unset($this->safeHtml);
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['rendered', 'raw'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }
}
