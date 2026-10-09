<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Application\Applicant\GeneratedCvSpec;

/** What a CV is made with: the template (default when missing), the language (`id` or `en`; the account language when missing) and the optional details to print. */
final class GenerateCvRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public static function specRules(string $prefix = ''): array
    {
        return [
            $prefix.'template' => ['nullable', 'string', 'max:30'],
            $prefix.'language' => ['nullable', 'string', 'in:id,en'],
            $prefix.'include' => ['nullable', 'array'],
            $prefix.'include.*' => ['boolean'],
        ];
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return self::specRules();
    }

    public function spec(): GeneratedCvSpec
    {
        return self::specFrom($this->input());
    }

    /** @param  array<string, mixed>  $input */
    public static function specFrom(array $input): GeneratedCvSpec
    {
        $include = [];

        foreach ((array) ($input['include'] ?? []) as $key => $on) {
            if (filter_var($on, FILTER_VALIDATE_BOOLEAN)) {
                $include[] = (string) $key;
            }
        }

        return new GeneratedCvSpec(
            isset($input['template']) && is_string($input['template']) ? $input['template'] : null,
            isset($input['language']) && is_string($input['language']) ? $input['language'] : null,
            $include,
        );
    }
}
