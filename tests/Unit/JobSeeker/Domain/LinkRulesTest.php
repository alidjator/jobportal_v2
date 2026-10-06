<?php

namespace Tests\Unit\JobSeeker\Domain;

use PHPUnit\Framework\TestCase;
use Src\JobSeeker\Domain\LinkRules;
use Src\JobSeeker\Domain\LinkType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class LinkRulesTest extends TestCase
{
    private function refused(LinkType $type, string $url): void
    {
        try {
            LinkRules::check($type, $url);
            $this->fail("The address was accepted: {$url}");
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('invalid-links', $e->errorCode());
            $this->assertArrayHasKey('url', $e->extensions()['errors']);
        }
    }

    public function test_a_plain_https_address_is_accepted_and_kept_as_given(): void
    {
        $link = LinkRules::check(LinkType::Portfolio, ' https://jane.example.test/work?x=1#top ');

        $this->assertSame('https://jane.example.test/work?x=1#top', $link->url);
        $this->assertSame(LinkType::Portfolio, $link->type);
        $this->assertSame('https://jane.example.test:443/', LinkRules::check(LinkType::Website, 'https://jane.example.test:443/')->url);
    }

    public function test_the_address_must_be_a_full_https_address_without_credentials_or_a_special_port(): void
    {
        foreach ([
            'http://jane.example.test', 'ftp://jane.example.test', 'jane.example.test', '//jane.example.test', 'javascript:alert(1)', 'https://', 'https:///path',
            'https://user@jane.example.test', 'https://user:pass@jane.example.test', 'https://jane.example.test:8443/', 'https://jane.example.test:80/',
        ] as $url) {
            $this->refused(LinkType::Website, $url);
        }
    }

    public function test_an_address_without_a_domain_name_is_refused(): void
    {
        foreach (['https://192.168.0.1/cv', 'https://8.8.8.8', 'https://[::1]/cv', 'https://localhost/cv', 'https://intranet/cv'] as $url) {
            $this->refused(LinkType::Portfolio, $url);
        }
    }

    public function test_known_url_shorteners_are_refused_on_every_kind_of_link(): void
    {
        foreach (['https://bit.ly/abc', 'https://tinyurl.com/abc', 'https://t.co/abc', 'https://www.bit.ly/abc', 'https://lnkd.in/abc', 'https://s.id/abc'] as $url) {
            $this->refused(LinkType::Portfolio, $url);
            $this->refused(LinkType::Other, $url);
        }

        $this->assertSame('https://notbit.ly.example.test/', LinkRules::check(LinkType::Website, 'https://notbit.ly.example.test/')->url);
    }

    public function test_the_social_kinds_must_point_to_their_own_domain(): void
    {
        foreach ([
            [LinkType::LinkedIn, 'https://www.linkedin.com/in/jane', 'https://evil.example.test/in/jane'],
            [LinkType::GitHub, 'https://github.com/jane', 'https://github.com.evil.example.test/jane'],
            [LinkType::Instagram, 'https://instagram.com/jane', 'https://notinstagram.com/jane'],
            [LinkType::X, 'https://x.com/jane', 'https://example.test/x.com'],
        ] as [$type, $good, $bad]) {
            $this->assertSame($good, LinkRules::check($type, $good)->url);
            $this->refused($type, $bad);
        }

        $this->assertSame('https://twitter.com/jane', LinkRules::check(LinkType::X, 'https://twitter.com/jane')->url);
        $this->assertSame('https://id.linkedin.com/in/jane', LinkRules::check(LinkType::LinkedIn, 'https://id.linkedin.com/in/jane')->url);
    }

    public function test_the_address_is_at_most_255_characters(): void
    {
        $this->assertSame(255, mb_strlen(LinkRules::check(LinkType::Website, 'https://jane.example.test/'.str_repeat('a', 229))->url));
        $this->refused(LinkType::Website, 'https://jane.example.test/'.str_repeat('a', 230));
    }

    public function test_a_list_has_at_most_eight_links_one_per_kind_and_three_others(): void
    {
        $ok = [
            ['type' => LinkType::LinkedIn, 'url' => 'https://linkedin.com/in/jane'], ['type' => LinkType::GitHub, 'url' => 'https://github.com/jane'],
            ['type' => LinkType::Other, 'url' => 'https://a.example.test'], ['type' => LinkType::Other, 'url' => 'https://b.example.test'], ['type' => LinkType::Other, 'url' => 'https://c.example.test'],
        ];
        $this->assertCount(5, LinkRules::checkAll($ok));
        $this->assertSame([], LinkRules::checkAll([]));

        $this->expectException(BusinessRuleViolation::class);
        LinkRules::checkAll([...$ok, ['type' => LinkType::Other, 'url' => 'https://d.example.test']]);
    }

    public function test_every_problem_of_a_list_is_reported_with_its_position(): void
    {
        try {
            LinkRules::checkAll([
                ['type' => LinkType::LinkedIn, 'url' => 'https://linkedin.com/in/jane'],
                ['type' => LinkType::LinkedIn, 'url' => 'https://linkedin.com/in/jane2'],
                ['type' => LinkType::GitHub, 'url' => 'http://github.com/jane'],
            ]);
            $this->fail('The list was accepted.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('invalid-links', $e->errorCode());
            $this->assertSame(['links.1.type', 'links.2.url'], array_keys($e->extensions()['errors']));
        }
    }

    public function test_more_than_eight_links_are_refused(): void
    {
        $links = array_map(fn (int $n): array => ['type' => LinkType::Other, 'url' => "https://site{$n}.example.test"], range(1, 9));

        try {
            LinkRules::checkAll($links);
            $this->fail('The list was accepted.');
        } catch (BusinessRuleViolation $e) {
            $this->assertArrayHasKey('links', $e->extensions()['errors']);
        }
    }
}
