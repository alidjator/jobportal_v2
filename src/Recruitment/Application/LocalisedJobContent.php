<?php

namespace Src\Recruitment\Application;

/**
 * The copy of the job content an application keeps (`recruitment_applications.job_snapshot`) in one language: the names of skills, benefits and the salary period are stored in both
 * languages and come out as a text in the language asked for (the other one when the item has no name in it).
 */
final class LocalisedJobContent
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function of(array $snapshot, string $language): array
    {
        foreach (['skills', 'benefits'] as $list) {
            $snapshot[$list] = array_map(static fn (array $item): array => ['id' => $item['id'], 'name' => self::pick($item['name'] ?? [], $language)], (array) ($snapshot[$list] ?? []));
        }

        if (is_array($snapshot['salary'] ?? null) && is_array($snapshot['salary']['period'] ?? null)) {
            $snapshot['salary']['period'] = ['id' => $snapshot['salary']['period']['id'], 'name' => self::pick($snapshot['salary']['period']['name'] ?? [], $language)];
        }

        return $snapshot;
    }

    /** @param  array<string, string>  $names */
    private static function pick(array $names, string $language): ?string
    {
        return $names[$language] ?? $names['id'] ?? ($names === [] ? null : (string) reset($names));
    }
}
