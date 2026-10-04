<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The version of each document in force that the user saw. Send only the types that are in force. */
final class AcceptTermsRequest extends FormRequest
{
    /** @var array<string, string> request field mapped to document type */
    private const array FIELDS = ['terms_version' => 'terms', 'privacy_version' => 'privacy'];

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'terms_version' => ['nullable', 'integer', 'min:1'],
            'privacy_version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, int> document type mapped to the version sent */
    public function versions(): array
    {
        $versions = [];

        foreach (self::FIELDS as $field => $type) {
            if ($this->filled($field)) {
                $versions[$type] = $this->integer($field);
            }
        }

        return $versions;
    }
}
