<?php

namespace Src\JobSeeker\Domain;

/** The kind of a profile link. The values match the check constraint of `jobseeker_links.type`. */
enum LinkType: string
{
    case LinkedIn = 'linkedin';
    case GitHub = 'github';
    case Portfolio = 'portfolio';
    case Website = 'website';
    case Instagram = 'instagram';
    case X = 'x';
    case Other = 'other';

    /**
     * The domains this kind of link must point to (a subdomain such as `www` is fine), or null when any domain is allowed.
     *
     * @return list<string>|null
     */
    public function allowedDomains(): ?array
    {
        return match ($this) {
            self::LinkedIn => ['linkedin.com'],
            self::GitHub => ['github.com'],
            self::Instagram => ['instagram.com'],
            self::X => ['x.com', 'twitter.com'],
            self::Portfolio, self::Website, self::Other => null,
        };
    }

    /** How many links of this kind a profile may have: one, except `other`. */
    public function maximum(): int
    {
        return $this === self::Other ? 3 : 1;
    }
}
