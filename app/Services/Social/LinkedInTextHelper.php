<?php

namespace App\Services\Social;

class LinkedInTextHelper
{
    /**
     * Convert HTML from a contenteditable post body to plain UTF-8 text
     * suitable for LinkedIn's commentary field.
     *
     * LinkedIn does not render HTML — tags appear literally and must not be
     * present in the commentary. This method:
     *   - Converts block-close tags and <br> to newlines
     *   - Strips all remaining HTML tags
     *   - Decodes HTML entities
     *   - Collapses runs of 3+ newlines to two
     *   - Trims leading/trailing whitespace
     */
    /**
     * Escape reserved "little text format" characters for LinkedIn's commentary field.
     *
     * Must be called AFTER htmlToLinkedInText() — operate on the final plain text,
     * not on HTML, or entity characters such as < > will be double-escaped.
     *
     * Rules (LinkedIn Posts API — "Little Text" format):
     *   - Backslash first, so added backslashes are not double-escaped.
     *   - | { } [ ] ( ) < > * _ ~ are always escaped.
     *   - # is escaped only when NOT starting a valid hashtag token (#Word).
     *   - @ is escaped only when NOT followed by a word character (prose "@").
     *
     * Hashtags appended by buildCommentary() are plain #Tokens — their leading #
     * is a valid hashtag, so they are NOT escaped by the conditional rules above.
     */
    public static function escapeForCommentary(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Escape backslash first to avoid double-escaping backslashes we add.
        $text = str_replace('\\', '\\\\', $text);

        foreach (['|', '{', '}', '[', ']', '(', ')', '<', '>', '*', '_', '~'] as $ch) {
            $text = str_replace($ch, '\\' . $ch, $text);
        }

        // '#' only when NOT the start of a valid hashtag token (letter/digit/underscore).
        $text = preg_replace('/#(?![\p{L}\p{N}_])/u', '\\#', $text) ?? $text;

        // '@' only when NOT followed by a word character.
        $text = preg_replace('/@(?![\p{L}\p{N}_])/u', '\\@', $text) ?? $text;

        return $text;
    }

    public static function htmlToLinkedInText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Fast path: no tags at all
        if (! str_contains($html, '<')) {
            return trim(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $text = str_replace(["\r\n", "\r"], "\n", $html);

        // Block-close tags → newline
        $text = preg_replace(
            '/<\/(?:p|div|h[1-6]|ul|ol|li|blockquote|pre|section|article|header|footer|main)>/i',
            "\n",
            $text
        ) ?? $text;

        // <br> and <br/> → newline
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;

        // Strip all remaining tags
        $text = strip_tags($text);

        // Decode HTML entities (&amp; &lt; &gt; &nbsp; &#x…; etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse 3+ consecutive newlines → two (one blank line between paragraphs)
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
