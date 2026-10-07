<?php

namespace Tests\Unit\LegacyImport;

use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Application\LegacyMarkdown;

final class LegacyMarkdownTest extends TestCase
{
    public function test_formatting_lists_headings_and_safe_links_are_kept(): void
    {
        $html = '<h3>Tasks</h3><p>We <strong>need</strong> <em>people</em>.</p><ul><li>One</li><li>Two <a href="https://example.test/a">link</a></li></ul><p><a href="mailto:hr@example.test">mail</a></p>';

        $this->assertSame("### Tasks\n\nWe **need** *people*.\n\n- One\n- Two [link](https://example.test/a)\n\n[mail](mailto:hr@example.test)", LegacyMarkdown::from($html));
    }

    public function test_scripts_styles_images_attributes_and_unsafe_links_are_dropped_but_their_text_stays(): void
    {
        $html = '<p style="color:red" onclick="x()">Hello <a href="javascript:alert(1)">bad link</a> <a href="/relative">relative</a> <a href="data:text/html;base64,AAAA">data</a></p><script>alert(1)</script><style>p{}</style><img src="https://example.test/x.png" alt="x"><iframe src="https://example.test"></iframe><table><tr><td>cell</td></tr></table>';

        $markdown = (string) LegacyMarkdown::from($html);

        $this->assertSame('Hello bad link relative data', explode("\n", $markdown)[0]);
        $this->assertStringContainsString('cell', $markdown);

        foreach (['javascript', 'alert', 'color', 'onclick', 'png', 'iframe', 'data:', '<'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $markdown);
        }
    }

    public function test_entities_are_decoded_and_no_raw_tag_can_appear(): void
    {
        $this->assertSame('a & b \<script>x\</script> 5 > 3', LegacyMarkdown::from('<p>a &amp; b &lt;script&gt;x&lt;/script&gt; 5 &gt; 3</p>'));
    }

    public function test_empty_or_blank_html_is_null(): void
    {
        $this->assertNull(LegacyMarkdown::from(null));
        $this->assertNull(LegacyMarkdown::from(''));
        $this->assertNull(LegacyMarkdown::from('  <p>&nbsp;</p> '));
    }
}
