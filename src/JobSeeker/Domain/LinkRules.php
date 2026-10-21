<?php

namespace Src\JobSeeker\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * The safety rules of a profile link: `https`, up to 255 characters, no credentials, no raw IP address, no non-standard port and no known URL shortener; the
 * typed social links must point to their own domain. Portfolio, website and other links may point anywhere else. The shortener list is short and in code.
 */
final class LinkRules
{
    public const int URL_MAX_LENGTH = 255;

    public const int LINKS_MAX = 8;

    /** @var list<string> */
    private const array SHORTENERS = [
        'bit.ly', 'bitly.com', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly', 'is.gd', 'buff.ly', 'rebrand.ly', 'cutt.ly', 'shorturl.at', 't.ly', 'rb.gy', 'lnkd.in',
        'tiny.cc', 's.id', 'v.gd', 'bl.ink', 'shorte.st', 'adf.ly',
    ];

    /**
     * The link when it is acceptable.
     *
     * @throws BusinessRuleViolation with the message to show under the url
     */
    public static function check(LinkType $type, string $url): ProfileLink
    {
        $url = trim($url);

        if (mb_strlen($url) > self::URL_MAX_LENGTH) {
            throw self::refused('The address is too long.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || strtolower($parts['scheme']) !== 'https') {
            throw self::refused('The address must be a complete https address.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw self::refused('The address must not hold a user name or a password.');
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw self::refused('The address must not use a special port.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.')) {
            throw self::refused('The address must use a domain name.');
        }

        if (self::isOneOf($host, self::SHORTENERS)) {
            throw self::refused('A shortened address is not allowed: give the full address.');
        }

        $allowed = $type->allowedDomains();

        if ($allowed !== null && ! self::isOneOf($host, $allowed)) {
            throw self::refused(new ErrorMessage('This kind of link must point to :domains.', ['domains' => implode(' or ', $allowed)]));
        }

        return new ProfileLink($type, $url);
    }

    /**
     * The links of a profile when the list is acceptable: at most 8, one per kind except `other` (up to 3), and every address checked. A profile that
     * already holds more than that (migrated as it was) may keep what it has: a list no longer than the `$stored` one, and a link that is still in it, pass.
     *
     * @param  list<array{type: LinkType, url: string}>  $links
     * @param  list<ProfileLink>  $stored
     * @return list<ProfileLink>
     *
     * @throws BusinessRuleViolation `invalid-links` with `errors` per `links.<n>.<field>` or `links`
     */
    public static function checkAll(array $links, array $stored = []): array
    {
        $errors = [];

        $storedPerType = [];

        foreach ($stored as $link) {
            $storedPerType[$link->type->value] = ($storedPerType[$link->type->value] ?? 0) + 1;
        }

        if (count($links) > self::LINKS_MAX && count($links) > count($stored)) {
            $errors['links'][] = new ErrorMessage('A profile can have at most :max links.', ['max' => self::LINKS_MAX]);
        }

        $perType = [];
        $checked = [];

        foreach ($links as $index => $link) {
            $perType[$link['type']->value] = ($perType[$link['type']->value] ?? 0) + 1;

            if ($perType[$link['type']->value] > $link['type']->maximum() && $perType[$link['type']->value] > ($storedPerType[$link['type']->value] ?? 0)) {
                $errors["links.{$index}.type"][] = $link['type']->maximum() === 1
                    ? 'Only one link of this kind is allowed.'
                    : new ErrorMessage('At most :max links of this kind are allowed.', ['max' => $link['type']->maximum()]);
            }

            if (self::isStored($stored, $link['type'], $link['url'])) {
                $checked[] = new ProfileLink($link['type'], trim($link['url']));

                continue;
            }

            try {
                $checked[] = self::check($link['type'], $link['url']);
            } catch (BusinessRuleViolation $violation) {
                $errors["links.{$index}.url"] = $violation->extensions()['errors']['url'];
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The links are not valid.', 'invalid-links', extensions: ['errors' => $errors]);
        }

        return $checked;
    }

    /** @param  list<string>  $domains */
    /** @param  list<ProfileLink>  $stored */
    private static function isStored(array $stored, LinkType $type, string $url): bool
    {
        foreach ($stored as $link) {
            if ($link->type === $type && $link->url === trim($url)) {
                return true;
            }
        }

        return false;
    }

    private static function isOneOf(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private static function refused(string|ErrorMessage $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-links', extensions: ['errors' => ['url' => [$message]]]);
    }
}
