<?php

namespace Tests\Unit\LegacyImport;

use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Application\LegacyText;
use Src\LegacyImport\Application\SlugMaker;

final class LegacyTextTest extends TestCase
{
    public function test_html_becomes_plain_text_with_line_breaks(): void
    {
        $html = '<p>We build <b>software</b>.</p><ul><li>Remote &amp; flexible</li><li>Fun</li></ul><script>alert(1)</script><style>p{color:red}</style><br>Join us';

        $this->assertSame("We build software.\nRemote & flexible\nFun\n\nJoin us", LegacyText::plain($html));
    }

    public function test_empty_or_markup_only_text_is_null(): void
    {
        $this->assertNull(LegacyText::plain(null));
        $this->assertNull(LegacyText::plain('<p>  </p><br>'));
        $this->assertSame('A B', LegacyText::plain("A \u{00A0}\t B"));
    }

    public function test_long_text_is_cut_without_breaking_a_character(): void
    {
        $this->assertSame('Sehat', LegacyText::limit('Sehat sekali', 5));
        $this->assertSame('Ké', LegacyText::limit('Kérja', 2));
        $this->assertNull(LegacyText::limit(null, 5));
    }

    public function test_clean_trims_and_turns_blank_into_null(): void
    {
        $this->assertSame('x', LegacyText::clean('  x '));
        $this->assertNull(LegacyText::clean('   '));
        $this->assertNull(LegacyText::clean(null));
    }

    public function test_only_a_bcrypt_hash_counts_as_one(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);

        $this->assertTrue(LegacyText::isBcrypt($hash));
        $this->assertTrue(LegacyText::isBcrypt(str_replace('$2y$', '$2a$', $hash)));
        $this->assertFalse(LegacyText::isBcrypt(md5('secret')));
        $this->assertFalse(LegacyText::isBcrypt(''));
        $this->assertFalse(LegacyText::isBcrypt(null));
        $this->assertFalse(LegacyText::isBcrypt(substr($hash, 0, 40)));
    }

    public function test_a_slug_is_made_from_a_name(): void
    {
        $this->assertSame('pt-maju-jaya', SlugMaker::from('  PT. Maju  Jaya! ', 60));
        $this->assertSame('cafe-nusantara', SlugMaker::from('Café Nusantara', 60));
        $this->assertSame('pt-maju', SlugMaker::from('PT Maju Jaya Abadi', 7));
        $this->assertSame('', SlugMaker::from('!!!', 60));
    }
}
