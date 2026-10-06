<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\StageType;

/** The name and the system type of a new stage. Whether the type may have extra stages is a use case rule. */
final class CreateStageRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:60'],
            'type' => ['required', 'string', Rule::enum(StageType::class)],
        ];
    }

    public function name(): string
    {
        return (string) $this->string('name');
    }

    public function type(): StageType
    {
        return StageType::from((string) $this->string('type'));
    }
}
