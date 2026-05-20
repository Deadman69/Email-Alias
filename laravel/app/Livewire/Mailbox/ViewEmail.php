<?php

namespace App\Livewire\Mailbox;

use App\Enums\AuditEvent;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
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
    public int $emailId;

    public bool $showExternalImages = false;

    public string $viewMode = 'rendered';

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

        $html = preg_replace_callback(
            '/<a\s[^>]*>/i',
            function ($matches) {
                $tag = $matches[0];

                $tag = preg_replace('/\s+target=["\'][^"\']*["\']/i', '', $tag);
                $tag = preg_replace('/\s+rel=["\'][^"\']*["\']/i', '', $tag);

                return rtrim(rtrim($tag, '>'), '/') . ' target="_blank" rel="noopener noreferrer">';
            },
            $html
        );

        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $html);
        $html = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $html);
        $html = preg_replace('/<embed\b[^>]*>/is', '', $html);
        $html = preg_replace('/<form\b[^>]*>(.*?)<\/form>/is', '', $html);

        $html = preg_replace(
            '/<link\b[^>]*rel=["\']?stylesheet["\']?[^>]*>/i',
            '',
            $html
        );

        if (! $this->showExternalImages) {
            $html = preg_replace_callback(
                '/<img\s[^>]*>/i',
                function ($matches) {
                    $tag = $matches[0];

                    return preg_replace(
                        '/\ssrc=["\'](?!data:)(https?:\/\/[^"\']+)["\']/i',
                        ' src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-original-src="$1"',
                        $tag
                    );
                },
                $html
            );

            $html = preg_replace_callback(
                '/style=["\']([^"\']*)["\']/i',
                function ($matches) {
                    $style = preg_replace(
                        '/background-image\s*:\s*url\s*\((https?:\/\/[^)]+)\)/i',
                        'background-image:none',
                        $matches[1]
                    );

                    return 'style="' . $style . '"';
                },
                $html
            );
        }

        return $html;
    }

    #[Computed]
    public function hasBlockedExternalContent(): bool
    {
        $html = $this->email->body_html ?? '';
        if (empty($html)) {
            return false;
        }

        if (preg_match('/<img[^>]+src=["\']https?:\/\//i', $html)) {
            return true;
        }

        if (preg_match('/background-image\s*:\s*url\s*\(\s*https?:\/\//i', $html)) {
            return true;
        }
        
        if (preg_match('/<link\b[^>]*rel=["\']?stylesheet["\']?[^>]*href=["\']https?:\/\//i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * Allow loading external images for this session.
     */
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