<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/** The names of master values and places for the candidate views, read in one query per master. A value is `{id, name}`, or null when none was given. */
final readonly class CandidateLabels
{
    public function __construct(private ReferenceValues $references, private LocationReferences $locations) {}

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    public function names(string $master, array $ids, string $language): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));

        return $ids === [] ? [] : $this->references->names($master, $ids, $language);
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    public function provinces(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));

        return $ids === [] ? [] : $this->locations->provinceNames($ids);
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    public function cities(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));

        return $ids === [] ? [] : $this->locations->cityNames($ids);
    }

    /**
     * @param  array<int, string>  $names
     * @return array{id: int, name: string|null}|null
     */
    public static function named(array $names, ?int $id): ?array
    {
        return $id === null ? null : ['id' => $id, 'name' => $names[$id] ?? null];
    }
}
