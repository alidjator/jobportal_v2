<?php

namespace Src\Company\Domain;

/**
 * Finds the link or email address in a message that a company may not send to a candidate: anything that points outside the company's own domain (the host of
 * its website, and its subdomains). A phone number is not a link. A bare domain is recognised by its ending (`.com`, `.co.id`, `.link`, ...), so a sentence
 * written without a space after a full stop is not mistaken for one.
 */
final class MessageLinkRule
{
    private const string TOP_LEVEL = 'com|net|org|info|biz|id|co|io|me|ly|link|app|dev|xyz|top|site|online|store|shop|club|live|vip|cc|tv|us|uk|sg|my|asia|ws|gl|gg|to|tk|ml|ga|cf|gq|page|website|tech|cloud|click|work|jobs|cyou|icu|buzz|fun';

    /** The first link or address that is not of the company's domain, as written, or null when the text has none. */
    public static function foreignLink(string $text, ?string $companyDomain): ?string
    {
        $domain = $companyDomain === null ? null : self::bareHost($companyDomain);

        foreach (self::candidates($text) as $candidate) {
            $host = self::hostOf($candidate);

            if ($host === null || $domain === null || ! self::within($host, $domain)) {
                return $candidate;
            }
        }

        return null;
    }

    /** The host of a website address as stored, without `www.`, in lower case; null when it has none. */
    public static function domainOf(?string $website): ?string
    {
        if ($website === null || trim($website) === '') {
            return null;
        }

        $host = parse_url(str_contains($website, '://') ? $website : 'https://'.$website, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::bareHost($host) : null;
    }

    /** @return list<string> */
    private static function candidates(string $text): array
    {
        $found = [];

        // An email address, a URL with a scheme or `www.`, or a bare domain with a known ending (and an optional path).
        $pattern = '~(?<![\w@.-])(?:[\w.+-]+@[\w-]+(?:\.[\w-]+)+|(?:[a-z][a-z0-9+.-]*://|www\.)[^\s<>"\']+|(?:[a-z0-9-]+\.)+(?:'.self::TOP_LEVEL.')(?![\w-])(?:[/?#][^\s<>"\']*)?)~iu';

        if (preg_match_all($pattern, $text, $matches) > 0) {
            foreach ($matches[0] as $match) {
                $found[] = rtrim($match, '.,;:!?)]}');
            }
        }

        return $found;
    }

    private static function hostOf(string $candidate): ?string
    {
        if (str_contains($candidate, '@') && ! str_contains($candidate, '://')) {
            return strtolower(substr($candidate, (int) strrpos($candidate, '@') + 1));
        }

        $host = parse_url(str_contains($candidate, '://') ? $candidate : 'https://'.$candidate, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private static function within(string $host, string $domain): bool
    {
        $host = self::bareHost($host);

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    private static function bareHost(string $host): string
    {
        $host = strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
