<?php

namespace Src\Organization\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** A read of units, positions or cost centres: `legal_entity_id` for a list (none on a `single` installation) and `as_of` (a day, today by default) for units and positions. */
final class StructureReadRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['legal_entity_id' => ['nullable', 'integer', 'min:1'], 'as_of' => ['nullable', 'date_format:Y-m-d']];
    }

    /** The legal entity the call is about; none on a `single` installation, which has one (`StructureEntity`). */
    public function legalEntityId(): ?int
    {
        return $this->filled('legal_entity_id') ? $this->integer('legal_entity_id') : null;
    }

    public function asOf(): ?DateTimeImmutable
    {
        return $this->filled('as_of') ? new DateTimeImmutable($this->string('as_of')->toString()) : null;
    }
}
