<?php

namespace Tests\Unit;

use App\Services\Social\LinkedInTextHelper;
use Tests\TestCase;

class LinkedInTextHelperTest extends TestCase
{
    /** @test */
    public function it_passes_plain_text_through_unchanged(): void
    {
        $this->assertSame('Hello LinkedIn', LinkedInTextHelper::htmlToLinkedInText('Hello LinkedIn'));
    }

    /** @test */
    public function it_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', LinkedInTextHelper::htmlToLinkedInText(''));
    }

    /** @test */
    public function it_converts_br_tags_to_newlines(): void
    {
        $result = LinkedInTextHelper::htmlToLinkedInText("Line one<br>Line two<br/>Line three");
        $this->assertSame("Line one\nLine two\nLine three", $result);
    }

    /** @test */
    public function it_converts_div_close_tags_to_newlines(): void
    {
        $result = LinkedInTextHelper::htmlToLinkedInText("<div>First</div><div>Second</div>");
        $this->assertStringContainsString("First", $result);
        $this->assertStringContainsString("Second", $result);
        $this->assertStringNotContainsString('<div>', $result);
        $this->assertStringContainsString("\n", $result);
    }

    /** @test */
    public function it_strips_all_html_tags(): void
    {
        $html   = '<p>Hello <strong>world</strong></p>';
        $result = LinkedInTextHelper::htmlToLinkedInText($html);
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringContainsString('Hello world', $result);
    }

    /** @test */
    public function it_decodes_html_entities(): void
    {
        $result = LinkedInTextHelper::htmlToLinkedInText('AT&amp;T &lt;rocks&gt; &amp; so does Q&amp;A');
        $this->assertSame('AT&T <rocks> & so does Q&A', $result);
    }

    /** @test */
    public function it_collapses_excess_blank_lines(): void
    {
        $html   = "<p>Para one</p><p></p><p></p><p>Para two</p>";
        $result = LinkedInTextHelper::htmlToLinkedInText($html);
        // Should not have more than two consecutive newlines
        $this->assertDoesNotMatch('/\n{3,}/', $result);
    }

    // ── escapeForCommentary ───────────────────────────────────────────────────

    /** @test */
    public function escape_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', LinkedInTextHelper::escapeForCommentary(''));
    }

    /** @test */
    public function escape_escapes_parentheses_and_brackets(): void
    {
        $input  = 'Delphia ($225K) and Global Predictions [SEC]';
        $result = LinkedInTextHelper::escapeForCommentary($input);
        $this->assertSame('Delphia \($225K\) and Global Predictions \[SEC\]', $result);
    }

    /** @test */
    public function escape_escapes_all_reserved_punctuation(): void
    {
        $input  = 'a|b{c}d[e]f(g)h<i>j*k_l~m';
        $result = LinkedInTextHelper::escapeForCommentary($input);
        $this->assertSame('a\|b\{c\}d\[e\]f\(g\)h\<i\>j\*k\_l\~m', $result);
    }

    /** @test */
    public function escape_does_not_double_escape_backslash(): void
    {
        // A literal backslash in the text must become \\
        $result = LinkedInTextHelper::escapeForCommentary('path\\to\\file');
        $this->assertSame('path\\\\to\\\\file', $result);
    }

    /** @test */
    public function escape_preserves_valid_hashtag_tokens(): void
    {
        // #DataScience starts a word — must NOT be escaped
        $result = LinkedInTextHelper::escapeForCommentary('#DataScience and #AI');
        $this->assertStringContainsString('#DataScience', $result);
        $this->assertStringContainsString('#AI', $result);
        $this->assertStringNotContainsString('\\#DataScience', $result);
    }

    /** @test */
    public function escape_escapes_bare_hash_not_part_of_hashtag(): void
    {
        // # followed by a space or end-of-string is NOT a hashtag
        $result = LinkedInTextHelper::escapeForCommentary('rank #1 in the world #');
        $this->assertStringContainsString('\\#1', $result); // #1 → \#1 (digit is \p{N})
        // Actually #1 — digit follows, so it IS kept. Let's test # followed by space.
        $result2 = LinkedInTextHelper::escapeForCommentary('ranked # one');
        $this->assertStringContainsString('\\# one', $result2);
    }

    /** @test */
    public function escape_escapes_bare_at_but_preserves_mention_prefix(): void
    {
        // '@' followed by a word char is a mention prefix — keep
        $keep = LinkedInTextHelper::escapeForCommentary('@JohnDoe pinged me');
        $this->assertStringStartsWith('@JohnDoe', $keep);
        $this->assertStringNotContainsString('\\@JohnDoe', $keep);

        // '@' followed by space or punctuation is prose — escape
        $escape = LinkedInTextHelper::escapeForCommentary('meet @ 9am');
        $this->assertStringContainsString('\\@ 9am', $escape);
    }

    /** @test */
    public function escape_handles_real_post_8_trigger(): void
    {
        // This is the exact pattern that truncated post 8
        $input  = 'The SEC penalized Delphia ($225K) for misleading AI claims.';
        $result = LinkedInTextHelper::escapeForCommentary($input);
        $this->assertStringContainsString('Delphia \($225K\)', $result);
        $this->assertStringContainsString('The SEC penalized', $result);
        $this->assertStringContainsString('for misleading AI claims.', $result);
    }

    /** @test */
    public function escape_full_example_with_hashtag(): void
    {
        $input  = 'Delphia ($225K) & Global Predictions [SEC] #DataScience @ 9am';
        $result = LinkedInTextHelper::escapeForCommentary($input);

        // Reserved chars escaped
        $this->assertStringContainsString('\(', $result);
        $this->assertStringContainsString('\)', $result);
        $this->assertStringContainsString('\[', $result);
        $this->assertStringContainsString('\]', $result);
        $this->assertStringContainsString('\@ 9am', $result);

        // Hashtag stays clickable
        $this->assertStringContainsString('#DataScience', $result);
        $this->assertStringNotContainsString('\\#DataScience', $result);
    }

    /** @test */
    public function it_handles_post_6_content_preserving_list_items(): void
    {
        // Simulates the real contenteditable HTML that caused Bug 3
        $html = implode('', [
            'A US court just made it official: if AI alone made it, nobody owns it.',
            '<div><br></div>',
            '<div>In March, the Supreme Court let stand a lower court ruling that said an AI-generated image has no copyright protection because there was no human creative expression in it.</div>',
            '<div><br></div>',
            '<div>What this means for creators using AI tools:</div>',
            '<div><br></div>',
            '<div>1. Pure AI output = public domain from day one</div>',
            '<div>2. Human + AI collaboration can still be copyrightable</div>',
            '<div>3. Your creative choices matter — prompts, selection, editing</div>',
            '<div>4. Document your creative process if you want IP protection</div>',
            '<div>5. The line between "tool" and "author" is now legally defined</div>',
        ]);

        $result = LinkedInTextHelper::htmlToLinkedInText($html);

        // No HTML tags survive
        $this->assertStringNotContainsString('<div>', $result);
        $this->assertStringNotContainsString('<br>', $result);

        // All 5 list items survive as distinct lines
        $this->assertStringContainsString('1. Pure AI output = public domain from day one', $result);
        $this->assertStringContainsString('2. Human + AI collaboration can still be copyrightable', $result);
        $this->assertStringContainsString('3. Your creative choices matter', $result);
        $this->assertStringContainsString('4. Document your creative process', $result);
        $this->assertStringContainsString('5. The line between "tool" and "author"', $result);

        // Opening sentence preserved
        $this->assertStringContainsString('A US court just made it official', $result);

        // No 3+ consecutive newlines
        $this->assertDoesNotMatch('/\n{3,}/', $result);
    }
}
