<?php

namespace Tests\Unit\Company;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Company\Domain\MessageLinkRule;

final class MessageLinkRuleTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function refused(): array
    {
        return [
            'a url' => ['Pay at https://evil.example/pay now', 'https://evil.example/pay'],
            'a url with a trailing full stop' => ['Open https://evil.com.', 'https://evil.com'],
            'a www address' => ['Go to www.evil.com today', 'www.evil.com'],
            'a bare domain' => ['Send your CV to evil.com/apply', 'evil.com/apply'],
            'a shortener' => ['See bit.ly/abc123', 'bit.ly/abc123'],
            'an email address' => ['Mail me at hr@gmail.com please', 'hr@gmail.com'],
            'a look-alike of the company domain' => ['Open https://maju.example.evil.com/x', 'https://maju.example.evil.com/x'],
            'a domain that only ends like the company one' => ['Mail hr@notmaju.example', 'hr@notmaju.example'],
        ];
    }

    #[DataProvider('refused')]
    public function test_a_link_outside_the_company_domain_is_found(string $text, string $link): void
    {
        $this->assertSame($link, MessageLinkRule::foreignLink($text, 'maju.example'));
    }

    /** @return array<string, array{string}> */
    public static function allowed(): array
    {
        return [
            'plain words' => ['Please bring your ID card and arrive 15 minutes early.'],
            'a phone number' => ['Call 021 555 0100 or +62 812-3456-7890 when you arrive.'],
            'a time and a version' => ['Come at 10.30 WIB, floor 3.'],
            'an abbreviation' => ['Bring e.g. your portfolio, etc. and a pen.'],
            'a sentence without a space after the full stop' => ['See you there.Thanks'],
            'the company site' => ['More at https://www.maju.example/careers'],
            'a page of the company site without a scheme' => ['Read www.maju.example/careers first.'],
            'a subdomain of the company site' => ['Open https://jobs.maju.example/room'],
            'a mailbox of the company' => ['Questions: hr@maju.example'],
        ];
    }

    #[DataProvider('allowed')]
    public function test_other_text_passes(string $text): void
    {
        $this->assertNull(MessageLinkRule::foreignLink($text, 'maju.example'));
    }

    public function test_without_a_company_domain_every_link_is_refused(): void
    {
        $this->assertSame('https://maju.example', MessageLinkRule::foreignLink('Open https://maju.example', null));
        $this->assertSame('hr@maju.example', MessageLinkRule::foreignLink('Mail hr@maju.example', null));
        $this->assertNull(MessageLinkRule::foreignLink('No link here.', null));
    }

    public function test_the_domain_of_a_website_is_its_host_without_www(): void
    {
        $this->assertSame('maju.example', MessageLinkRule::domainOf('https://www.Maju.example/about'));
        $this->assertSame('maju.example', MessageLinkRule::domainOf('maju.example'));
        $this->assertNull(MessageLinkRule::domainOf(null));
        $this->assertNull(MessageLinkRule::domainOf('  '));
    }
}
