<?php

namespace Src\LegacyImport\Application;

use InvalidArgumentException;
use Src\Recruitment\Domain\StageType;

/**
 * The pipeline group mapping file, validated: a JSON object keyed by the legacy group id, each value `{"stage": "<type>", "create_stage": true|false}`
 * (`create_stage` is optional and only for the types that allow extra stages). The five default group names of the legacy application are mapped by name
 * when the file has no entry for the group; a group that is neither in the file nor a default name has no target.
 */
final readonly class StageGroupMapping
{
    /** The legacy default groups by lower-case name. */
    private const array DEFAULT_NAMES = [
        'no group' => StageType::Applied,
        'all applications' => StageType::Applied,
        'shortlisted' => StageType::Screening,
        'interview' => StageType::Interview,
        'rejected' => StageType::Rejected,
    ];

    /** @param  array<int, StageGroupTarget>  $entries */
    private function __construct(private array $entries) {}

    /**
     * @param  array<int|string, mixed>  $raw  the decoded mapping file
     *
     * @throws InvalidArgumentException naming the first entry that is not valid
     */
    public static function from(array $raw): self
    {
        $entries = [];

        foreach ($raw as $key => $value) {
            $label = 'Group mapping entry "'.$key.'"';

            // PHP turns a key such as "142" into an integer, so anything else (a word, "007", a negative number) is not a group id.
            if (! is_int($key) || $key < 1) {
                throw new InvalidArgumentException($label.' is not valid: the key must be a legacy group id.');
            }

            if (! is_array($value) || array_is_list($value) && $value !== []) {
                throw new InvalidArgumentException($label.' must be an object with "stage" and an optional "create_stage".');
            }

            $unknown = array_diff(array_keys($value), ['stage', 'create_stage']);

            if ($unknown !== []) {
                throw new InvalidArgumentException($label.' has an unknown field "'.reset($unknown).'".');
            }

            $type = is_string($value['stage'] ?? null) ? StageType::tryFrom($value['stage']) : null;

            if ($type === null) {
                throw new InvalidArgumentException($label.' has an unknown stage type; use one of: '.implode(', ', array_map(static fn (StageType $case): string => $case->value, StageType::cases())).'.');
            }

            $create = $value['create_stage'] ?? false;

            if (! is_bool($create)) {
                throw new InvalidArgumentException($label.' has a "create_stage" that is not true or false.');
            }

            if ($create && ! $type->allowsExtraStages()) {
                throw new InvalidArgumentException($label.' cannot create a stage of the type "'.$type->value.'"; only screening, assessment, interview and offer allow extra stages.');
            }

            $entries[(int) $key] = new StageGroupTarget($type, $create);
        }

        return new self($entries);
    }

    /**
     * Finds the duplicate keys of the top-level object of a JSON text (a decoded array cannot show them, the last one wins).
     *
     * @return list<string> the keys that appear more than once
     */
    public static function duplicateKeys(string $json): array
    {
        $seen = [];
        $duplicates = [];
        $depth = 0;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($char === '"') {
                $start = $i;
                $i++;

                while ($i < $length && $json[$i] !== '"') {
                    $i += $json[$i] === '\\' ? 2 : 1;
                }

                if ($depth === 1) {
                    $next = $i + 1;

                    while ($next < $length && ctype_space($json[$next])) {
                        $next++;
                    }

                    if (($json[$next] ?? '') === ':') {
                        $key = (string) json_decode(substr($json, $start, $i - $start + 1));

                        if (isset($seen[$key])) {
                            $duplicates[$key] = $key;
                        }

                        $seen[$key] = true;
                    }
                }
            } elseif ($char === '{' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ']') {
                $depth--;
            }
        }

        return array_values($duplicates);
    }

    /** The target of a legacy group: the file's entry, else the default name's, else null. */
    public function targetFor(int $groupId, ?string $groupName): ?StageGroupTarget
    {
        if (isset($this->entries[$groupId])) {
            return $this->entries[$groupId];
        }

        $type = self::DEFAULT_NAMES[mb_strtolower(trim((string) $groupName))] ?? null;

        return $type === null ? null : new StageGroupTarget($type, false);
    }
}
