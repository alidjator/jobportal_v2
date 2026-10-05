<?php

namespace Src\Company\Interface\Http\Requests;

use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve a document: the body names the submission the admin looked at (`submitted_at` of the admin list), so a replaced file is never approved unseen.
 * Any ISO 8601 timestamp with a time zone is accepted (`+00:00`, `+07:00` or `Z`) and it is compared as a moment.
 */
class ApproveCompanyDocumentRequest extends FormRequest
{
    private const string FORMAT = '!Y-m-d\TH:i:sP';

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'submitted_at' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || DateTimeImmutable::createFromFormat(self::FORMAT, $value) === false) {
                    $fail('The submitted at must be an ISO 8601 timestamp with a time zone, as given by the document list.');
                }
            }],
        ];
    }

    public function seenSubmittedAt(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::FORMAT, $this->string('submitted_at')->toString()) ?: throw new \LogicException('submitted_at was validated.');
    }
}
