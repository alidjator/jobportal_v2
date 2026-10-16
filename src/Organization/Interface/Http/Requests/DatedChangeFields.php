<?php

namespace Src\Organization\Interface\Http\Requests;

use DateTimeImmutable;

/** The two fields of a change to a unit or position: `valid_from` (a day, today by default) and `reason` (5 to 500 characters, needed when the day is in the past). */
trait DatedChangeFields
{
    /** @return array<string, list<string>> */
    private function datedChangeRules(): array
    {
        return ['valid_from' => ['nullable', 'date_format:Y-m-d'], 'reason' => ['nullable', 'string', 'min:5', 'max:500']];
    }

    public function validFrom(): ?DateTimeImmutable
    {
        return $this->filled('valid_from') ? new DateTimeImmutable($this->string('valid_from')->toString()) : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? trim($this->string('reason')->toString()) : null;
    }
}
