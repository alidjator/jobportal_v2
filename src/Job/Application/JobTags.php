<?php

namespace Src\Job\Application;

/**
 * The tag rules of a job: free keywords, trimmed, lower-cased, spaces collapsed, 2 to 30 characters from letters, digits, spaces and `+ # . -`, with
 * at least one letter or digit. Repeats (after normalising) count once; at most 10 tags.
 */
final class JobTags
{
    public const int MAX_TAGS = 10;

    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 30;

    /**
     * @param  list<string>  $raw
     * @return array{0: list<string>, 1: list<string>} the normalised tags in order, and the problems found (empty when all is well)
     */
    public static function normalize(array $raw): array
    {
        $tags = [];
        $problems = [];

        foreach ($raw as $text) {
            $tag = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
            $length = mb_strlen($tag);

            if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
                $problems[] = 'A tag must have 2 to 30 characters.';
            } elseif (! preg_match('/^[\p{L}\p{N}+#. \-]+$/u', $tag) || ! preg_match('/[\p{L}\p{N}]/u', $tag)) {
                $problems[] = 'A tag may hold letters, digits, spaces and + # . - only, and at least one letter or digit.';
            } else {
                $tags[$tag] = $tag;
            }
        }

        if (count($tags) > self::MAX_TAGS) {
            $problems[] = 'A job can have at most '.self::MAX_TAGS.' tags.';
        }

        return [array_values($tags), array_values(array_unique($problems))];
    }
}
