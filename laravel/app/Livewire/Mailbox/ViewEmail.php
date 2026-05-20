<?php

namespace App\Livewire\Mailbox;

use App\Enums\AuditEvent;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Email')]
class ViewEmail extends Component
{
    #[Locked]
    public int $emailId;

    /** Whether the user has unlocked external image loading. */
    public bool $showExternalImages = false;

    /**
     * Mount and authorize, then mark as read.
     */
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
        return InboundEmail::with('alias')->findOrFail($this->emailId);
    }

    /**
     * Prepare the HTML body for safe display in a sandboxed iframe.
     * Rewrites external image src to data-src when images are blocked.
     * Forces all links to open in a new tab.
     */
    #[Computed]
    public function safeHtml(): string
    {
        $html = $this->email->body_html ?? '';

        if (empty($html)) {
            return '';
        }

        // Rewrite links to always open in new tab
        $html = preg_replace_callback(
            '/<a\s[^>]*>/i',
            function ($matches) {
                $tag = $matches[0];
                // Remove existing target / rel
                $tag = preg_replace('/\s+target=["\'][^"\']*["\']/i', '', $tag);
                $tag = preg_replace('/\s+rel=["\'][^"\']*["\']/i', '', $tag);
                // Inject safe target + rel
                return rtrim(rtrim($tag, '>'), '/') . ' target="_blank" rel="noopener noreferrer">';
            },
            $html
        );

        if (! $this->showExternalImages) {
            // Replace external image src with data-original-src and a blank pixel
            $html = preg_replace_callback(
                '/<img\s[^>]*>/i',
                function ($matches) {
                    $tag = $matches[0];
                    // Only block http/https src (leave data: URIs untouched)
                    return preg_replace(
                        '/\ssrc=["\'](?!data:)(https?:\/\/[^"\']+)["\']/i',
                        ' src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-original-src="$1"',
                        $tag
                    );
                },
                $html
            );
        }

        return $html;
    }

    /**
     * Allow loading external images for this session.
     */
    public function allowExternalImages(): void
    {
        $this->showExternalImages = true;
        unset($this->safeHtml);
    }
}
