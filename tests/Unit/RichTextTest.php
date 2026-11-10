<?php

namespace Tests\Unit;

use App\Core\Content\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_keeps_basic_formatting(): void
    {
        $html = '<h2>Care</h2><p><strong>Bold</strong>, <em>italic</em>, <u>under</u> and <s>gone</s>.</p><ul><li><p>One</p></li></ul><ol><li>Two</li></ol><blockquote><p>Quote</p></blockquote><hr>';

        $this->assertSame(
            '<h2>Care</h2><p><strong>Bold</strong>, <em>italic</em>, <u>under</u> and <s>gone</s>.</p><ul><li><p>One</p></li></ul><ol><li>Two</li></ol><blockquote><p>Quote</p></blockquote><hr />',
            RichText::clean($html),
        );
    }

    public function test_strips_scripts_event_handlers_and_unsafe_links(): void
    {
        $clean = RichText::clean('<p onclick="alert(1)" style="color:red">Hi<script>alert(1)</script></p><a href="javascript:alert(1)">bad</a><img src=x onerror=alert(1)><iframe src="https://evil.test"></iframe>');

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('style', $clean);
        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('<p>Hi</p>', $clean);
    }

    public function test_links_keep_safe_href_and_get_rel(): void
    {
        $clean = RichText::clean('<p><a href="https://example.test/size" target="_blank" class="x">Size guide</a></p>');

        $this->assertStringContainsString('href="https://example.test/size"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
        $this->assertStringNotContainsString('class=', $clean);
    }

    public function test_unknown_tags_keep_their_text(): void
    {
        $this->assertSame('<p>Hello world</p>', RichText::clean('<div><p><span>Hello</span> world</p></div>'));
    }

    public function test_blank_values_become_null(): void
    {
        $this->assertNull(RichText::clean(null));
        $this->assertNull(RichText::clean('   '));
        $this->assertNull(RichText::clean('<p></p>'));
        $this->assertNull(RichText::clean('<p><br></p>'));
    }

    public function test_plain_text_is_stored_as_typed(): void
    {
        $this->assertSame("Line one\nLine two", RichText::clean("Line one\nLine two"));
    }

    public function test_plain_text_converts_to_paragraphs(): void
    {
        $this->assertSame(
            '<p>Size &lt; 5 &amp; up<br>still one</p><p>Second</p>',
            RichText::toHtml("Size < 5 & up\r\nstill one\n\n\nSecond"),
        );
        $this->assertNull(RichText::toHtml(''));
    }

    public function test_stored_html_is_cleaned_again_on_output(): void
    {
        $this->assertSame('<p>Hi</p>', RichText::toHtml('<p onclick="x()">Hi<script>x()</script></p>'));
    }

    public function test_html_converts_to_text(): void
    {
        $this->assertSame(
            "Care\nWash & dry\nOne\nTwo",
            RichText::toText('<h2>Care</h2><p>Wash &amp; dry</p><ul><li><p>One</p></li><li>Two</li></ul>'),
        );
        $this->assertSame("Plain\ntext", RichText::toText("Plain\ntext"));
        $this->assertNull(RichText::toText(null));
    }
}
