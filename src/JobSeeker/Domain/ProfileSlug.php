<?php

namespace Src\JobSeeker\Domain;

/** The permanent identifier of a profile in the company API: a word and eight random digits (`maple-48213907`), never built from the name. */
final class ProfileSlug
{
    private const array WORDS = [
        'amber', 'aspen', 'birch', 'bridge', 'brook', 'cedar', 'cliff', 'cloud', 'coral', 'creek', 'dawn', 'delta', 'dune', 'ember', 'fern', 'field',
        'fjord', 'flint', 'forest', 'frost', 'glade', 'grove', 'harbor', 'hazel', 'heath', 'hill', 'iris', 'island', 'jade', 'lagoon', 'lake', 'leaf',
        'lotus', 'maple', 'marsh', 'meadow', 'mist', 'moss', 'oak', 'ocean', 'olive', 'orchid', 'peak', 'pine', 'pond', 'rain', 'reef', 'ridge',
        'river', 'sage', 'sand', 'shore', 'sky', 'spring', 'stone', 'storm', 'summit', 'tide', 'valley', 'willow', 'wind', 'winter', 'wood', 'zephyr',
    ];

    public static function generate(): string
    {
        return self::WORDS[random_int(0, count(self::WORDS) - 1)].'-'.str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }
}
