<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobSeeker\Domain\LinkType;

/** The whole list of links. The count, the kinds per list and every address are checked by the use case (`LinkRules`). */
final class ReplaceMyLinksRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'links' => ['present', 'array', 'max:100'],
            'links.*' => ['array:type,url'],
            'links.*.type' => ['required', 'string', Rule::enum(LinkType::class)],
            'links.*.url' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return list<array{type: LinkType, url: string}> */
    public function links(): array
    {
        return array_values(array_map(
            static fn (array $item): array => ['type' => LinkType::from((string) $item['type']), 'url' => (string) $item['url']],
            $this->array('links'),
        ));
    }
}
