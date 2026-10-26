<?php

namespace Src\Assessment\Domain;

/**
 * A raw score range of one scale and the standard score and band it converts to, with the plain-language explanation of that band for the candidate (used by an
 * automatic report). The row may hold only for an age range and an education rank range; a row without them applies to everyone (`NormConverter` picks the most specific).
 */
final readonly class Norm
{
    public function __construct(
        public string $scaleKey,
        public int $rawMin,
        public int $rawMax,
        public float $standardScore,
        public string $band,
        public ?string $explanation = null,
        public ?int $ageMin = null,
        public ?int $ageMax = null,
        public ?int $educationRankMin = null,
        public ?int $educationRankMax = null,
    ) {}

    public function hasAgeGroup(): bool
    {
        return $this->ageMin !== null || $this->ageMax !== null;
    }

    public function hasEducationGroup(): bool
    {
        return $this->educationRankMin !== null || $this->educationRankMax !== null;
    }

    public function isGeneral(): bool
    {
        return ! $this->hasAgeGroup() && ! $this->hasEducationGroup();
    }

    /** Whether the group of this row holds the candidate: a restricted field the candidate does not carry does not match. */
    public function holdsFor(NormCandidate $candidate): bool
    {
        return self::within($candidate->age, $this->ageMin, $this->ageMax, $this->hasAgeGroup())
            && self::within($candidate->educationRank, $this->educationRankMin, $this->educationRankMax, $this->hasEducationGroup());
    }

    /** The key of the group: rows of one scale with the same key may not overlap in raw range. */
    public function groupKey(): string
    {
        return implode(':', [$this->ageMin ?? '', $this->ageMax ?? '', $this->educationRankMin ?? '', $this->educationRankMax ?? '']);
    }

    /** @return array{age_min: int|null, age_max: int|null, education_rank_min: int|null, education_rank_max: int|null}|null null for a row that applies to everyone */
    public function group(): ?array
    {
        return $this->isGeneral() ? null : [
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'education_rank_min' => $this->educationRankMin,
            'education_rank_max' => $this->educationRankMax,
        ];
    }

    private static function within(?int $value, ?int $min, ?int $max, bool $restricted): bool
    {
        if (! $restricted) {
            return true;
        }

        return $value !== null && ($min === null || $value >= $min) && ($max === null || $value <= $max);
    }
}
