<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes HTML email bodies for safe display.
 *
 * Uses HTMLPurifier to strip all dangerous content:
 *  - Event handlers (onerror, onload, onmouseover, etc.)
 *  - Script / iframe / object / embed / form tags
 *  - Dangerous CSS (javascript:, expression(), etc.)
 *  - External stylesheets
 *
 * Install: podman compose exec app composer require ezyang/htmlpurifier
 */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function __construct()
    {
        if (! class_exists(HTMLPurifier::class)) {
            return; // Graceful degradation until package is installed
        }

        $config = HTMLPurifier_Config::createDefault();

        // ── Allowed tags & attributes ─────────────────────────────────────────
        $config->set('HTML.Allowed',
            'a[href|target|rel|title],'
            . 'abbr[title],acronym[title],'
            . 'b,strong,i,em,u,s,strike,del,'
            . 'p[style|class],div[style|class],span[style|class],'
            . 'h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],'
            . 'ul[style],ol[style],li[style],'
            . 'br,hr[style],'
            . 'pre[style],code[style],'
            . 'blockquote[style|cite],'
            . 'table[style|cellpadding|cellspacing|border|width|align],'
            . 'thead,tbody,tfoot,'
            . 'tr[style],'
            . 'th[style|colspan|rowspan|align|valign],'
            . 'td[style|colspan|rowspan|align|valign],'
            . 'img[src|alt|width|height|style|title],'
            . 'font[color|face|size],'
            . 'center,small,big,'
            . 'caption[style]'
        );

        // ── Safe CSS properties ───────────────────────────────────────────────
        $config->set('CSS.AllowedProperties', [
            'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
            'color', 'background-color',
            'text-align', 'text-decoration', 'text-transform', 'text-indent',
            'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
            'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
            'border', 'border-color', 'border-style', 'border-width',
            'border-top', 'border-right', 'border-bottom', 'border-left',
            'border-collapse', 'border-spacing',
            'width', 'height', 'max-width', 'min-width', 'max-height', 'min-height',
            'line-height', 'letter-spacing', 'word-spacing',
            'display', 'vertical-align', 'float', 'clear',
            'list-style', 'list-style-type',
        ]);

        // Allow data: URIs for inline base64 images
        $config->set('URI.AllowedSchemes', [
            'http'   => true,
            'https'  => true,
            'mailto' => true,
            'data'   => true,
        ]);

        // Force target="_blank" on all links
        $config->set('HTML.TargetBlank', true);

        // Disable cache to avoid filesystem permission issues
        // Enable in production: $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier'))
        $config->set('Cache.DefinitionImpl', null);

        $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Sanitize HTML, blocking external images (anti-tracking).
     * Replaces external img src with a 1px placeholder and stores the original in data-original-src.
     */
    public function sanitize(string $html, bool $blockExternalImages = true): string
    {
        if (empty($html)) {
            return '';
        }

        if ($this->purifier === null) {
            // Fallback: basic regex strip when HTMLPurifier is not installed
            return $this->fallbackSanitize($html, $blockExternalImages);
        }

        // Block external images BEFORE purification to prevent bypass via CSS url()
        if ($blockExternalImages) {
            $html = $this->blockExternalImages($html);
        }

        $sanitized = $this->purifier->purify($html);

        // HTMLPurifier forces target="_blank" via config above,
        // but we also want rel="noopener noreferrer" on every link
        $sanitized = preg_replace(
            '/<a\b([^>]*)\btarget=["\']_blank["\']/i',
            '<a$1 target="_blank" rel="noopener noreferrer"',
            $sanitized
        );

        return $sanitized;
    }

    /**
     * Whether the raw HTML contains external content (images, CSS).
     */
    public function hasExternalContent(string $html): bool
    {
        if (empty($html)) {
            return false;
        }

        return (bool) preg_match('/<img[^>]+src=["\']https?:\/\//i', $html)
            || (bool) preg_match('/background(?:-image)?\s*:\s*url\s*\(\s*https?:\/\//i', $html)
            || (bool) preg_match('/<link\b[^>]*rel=["\']?stylesheet["\']?[^>]*href=["\']https?:\/\//i', $html);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function blockExternalImages(string $html): string
    {
        // Replace external <img src="http..."> with placeholder
        $html = preg_replace_callback(
            '/<img(\s[^>]*)?\ssrc=["\'](?!data:)(https?:\/\/[^"\']+)["\']/i',
            static function (array $m) {
                $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                $original    = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');

                return '<img' . ($m[1] ?? '') . ' src="' . $placeholder . '" data-original-src="' . $original . '"';
            },
            $html
        );

        // Strip background-image: url(https://...) in style attributes
        return preg_replace_callback(
            '/style=["\']([^"\']*)["\']/',
            static function (array $m) {
                $style = preg_replace(
                    '/background(?:-image)?\s*:\s*url\s*\(\s*["\']?https?:\/\/[^)]+["\']?\s*\)/i',
                    'background-image:none',
                    $m[1]
                );

                return 'style="' . $style . '"';
            },
            $html
        );
    }

    /**
     * Basic regex-based fallback used when HTMLPurifier is not installed.
     * Less robust — install the package for proper protection.
     */
    private function fallbackSanitize(string $html, bool $blockExternalImages): string
    {
        // Strip dangerous tags
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $html);
        $html = preg_replace('/<embed\b[^>]*>/is', '', $html);
        $html = preg_replace('/<form\b[^>]*>.*?<\/form>/is', '', $html);
        $html = preg_replace('/<link\b[^>]*rel=["\']?stylesheet["\']?[^>]*>/i', '', $html);

        // Strip ALL inline event handlers (on*)
        $html = preg_replace('/\s+on\w+=["\'][^"\']*["\']/i', '', $html);

        // Force links to open in new tab
        $html = preg_replace_callback('/<a\s[^>]*>/i', static function (array $m) {
            $tag = preg_replace('/\s+target=["\'][^"\']*["\']/i', '', $m[0]);
            $tag = preg_replace('/\s+rel=["\'][^"\']*["\']/i', '', $tag);
            return rtrim(rtrim($tag, '>'), '/') . ' target="_blank" rel="noopener noreferrer">';
        }, $html);

        if ($blockExternalImages) {
            $html = $this->blockExternalImages($html);
        }

        return $html;
    }
}
