<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Rich-text rendering + sanitization for the long-text fields that are now
 * edited with the Quill WYSIWYG editor (opportunity description/notes, contact
 * notes, document description, email signature/account notes, follow-up bodies).
 *
 * `toHtml()` is the single display-side chokepoint: it accepts whatever is in
 * the column — Quill HTML, legacy Markdown, or legacy plain text — and always
 * returns safe HTML. Because some of these fields are written directly by the
 * AI/MCP pipeline (never passing through a form), sanitizing here guarantees
 * nothing unsafe ever reaches the browser regardless of source.
 *
 * `sanitize()` is a self-contained allowlist cleaner built on ext-dom (always
 * available). It deliberately mirrors what an HTMLPurifier "richtext" profile
 * would permit, tuned to Quill's known output, so we avoid adding a Composer
 * dependency the locked deploy pipeline can't install.
 */
class RichText
{
    /** Tag => list of attributes permitted on that tag. */
    private const ALLOWED = [
        'p' => ['class', 'style'], 'br' => [], 'hr' => [],
        'span' => ['style', 'class'], 'div' => ['style', 'class'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'del' => [], 'strike' => [], 'sub' => [], 'sup' => [],
        'h1' => ['class'], 'h2' => ['class'], 'h3' => ['class'],
        'h4' => ['class'], 'h5' => ['class'], 'h6' => ['class'],
        'blockquote' => ['class'], 'pre' => ['class'], 'code' => ['class'],
        'ol' => ['class'], 'ul' => ['class'], 'li' => ['class'],
        'a' => ['href', 'title', 'target', 'rel', 'class'],
    ];

    /** Elements removed wholesale (content discarded), not just unwrapped. */
    private const STRIP_ELEMENTS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'textarea', 'button', 'select', 'option', 'link', 'meta', 'base',
        'svg', 'math', 'noscript', 'template',
    ];

    private const ALLOWED_STYLE_PROPS = ['color', 'background-color', 'text-align'];

    /**
     * Render a stored value as safe display HTML. Handles Quill HTML, legacy
     * Markdown, and legacy plain text transparently.
     */
    public static function toHtml(?string $value): string
    {
        $value = (string) $value;
        if (trim($value) === '') {
            return '';
        }

        // Already HTML (Quill output, or HTML written by the pipeline).
        if (preg_match('/<\/?[a-z][\s\S]*>/i', $value)) {
            return self::collapseEmpty(self::sanitize($value));
        }

        // Legacy Markdown — same detection used previously on the show pages.
        if (preg_match('/(^|\n)\s*#{1,6}\s|(^|\n)\s*[-*]\s+|(^|\n)\s*\d+\.\s+|\*\*[^*]+\*\*|`[^`]+`|\[[^\]]+\]\([^)]+\)/', $value)) {
            $md = Str::markdown($value, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

            return self::collapseEmpty(self::sanitize($md));
        }

        // Plain text — escape and preserve line breaks.
        return nl2br(e($value), false);
    }

    /**
     * Allowlist-sanitize a fragment of HTML. Safe to call on already-clean
     * input (idempotent) and on the save path before persisting.
     */
    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML PI forces UTF-8 parsing; NOIMPLIED/NODEFDTD keep libxml from
        // wrapping the fragment in <html><body> or adding a doctype.
        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div data-rt-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;
        foreach ($doc->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $root = $node;
                break;
            }
        }
        if (! $root) {
            return '';
        }

        self::cleanChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /** Recursively sanitize the children of a node in place. */
    private static function cleanChildren(\DOMNode $node): void
    {
        // Snapshot first — we mutate the live child list while iterating.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode->removeChild($child);
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue; // text nodes are escaped on serialize — leave as-is
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::STRIP_ELEMENTS, true)) {
                $child->parentNode->removeChild($child);
                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Unknown-but-harmless tag: clean its subtree, then unwrap it so
                // we keep the text content rather than dropping it.
                self::cleanChildren($child);
                while ($child->firstChild) {
                    $child->parentNode->insertBefore($child->firstChild, $child);
                }
                $child->parentNode->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
            self::cleanChildren($child);
        }
    }

    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $keep = self::ALLOWED[$tag];

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);

            if (! in_array($name, $keep, true)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            if ($name === 'href') {
                if (! preg_match('#^\s*(https?:|mailto:|tel:|/|\#)#i', $attr->nodeValue)) {
                    $el->removeAttribute('href');
                }
            } elseif ($name === 'style') {
                $safe = self::filterStyle($attr->nodeValue);
                $safe === '' ? $el->removeAttribute('style') : $el->setAttribute('style', $safe);
            } elseif ($name === 'class') {
                $safe = self::filterClass($attr->nodeValue);
                $safe === '' ? $el->removeAttribute('class') : $el->setAttribute('class', $safe);
            } elseif ($name === 'target' || $name === 'rel') {
                // Normalized below for anchors; drop elsewhere.
                $el->removeAttribute($attr->nodeName);
            }
        }

        // Harden anchors that survived with a usable href.
        if ($tag === 'a' && $el->getAttribute('href') !== '') {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener nofollow');
        }
    }

    /** Keep only a small allowlist of CSS declarations with safe values. */
    private static function filterStyle(string $style): string
    {
        $out = [];
        foreach (explode(';', $style) as $decl) {
            if (! str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (! in_array($prop, self::ALLOWED_STYLE_PROPS, true)) {
                continue;
            }
            if ($val === '' || preg_match('/url\(|expression|javascript:|[<>"]/i', $val)) {
                continue;
            }
            $out[] = $prop . ': ' . $val;
        }

        return implode('; ', $out);
    }

    /** Keep only Quill's own ql-* utility classes. */
    private static function filterClass(string $class): string
    {
        $keep = array_filter(
            preg_split('/\s+/', trim($class)) ?: [],
            fn ($c) => $c !== '' && preg_match('/^ql-[a-z0-9-]+$/i', $c)
        );

        return implode(' ', $keep);
    }

    /** Treat a structurally-empty editor value (e.g. "<p><br></p>") as blank. */
    private static function collapseEmpty(string $html): string
    {
        $textual = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $textual = preg_replace('/\x{00a0}|\s+/u', '', $textual);

        // If there's no text AND no meaningful void/list content, it's empty.
        if ($textual === '' && ! preg_match('/<(li|img|hr|td|th)\b/i', $html)) {
            return '';
        }

        return $html;
    }
}
