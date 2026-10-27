<?php

namespace Src\Assessment\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\LicenceKind;

/**
 * A multipart upload: `kind` (`str` or `silp`), `number`, `valid_until` (a day) and `file`. Only the shape of the file is checked here; the use case reads its content and
 * ignores the type the client sent.
 */
final class SubmitLicenceRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::enum(LicenceKind::class)],
            'number' => ['required', 'string', 'min:3', 'max:60'],
            'valid_until' => ['required', 'date_format:Y-m-d'],
            'file' => ['required', 'file'],
        ];
    }

    public function kind(): LicenceKind
    {
        return LicenceKind::from($this->string('kind')->toString());
    }

    public function validUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string('valid_until')->toString(), new DateTimeZone('UTC'));
    }

    public function contents(): string
    {
        return (string) $this->file('file')?->getContent();
    }

    public function originalName(): string
    {
        return (string) $this->file('file')?->getClientOriginalName();
    }
}
