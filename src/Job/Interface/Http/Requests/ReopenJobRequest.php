<?php

namespace Src\Job\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/** The new deadline (a date) of the job to reopen. Whether it is in range is checked by the use case, with the company's limit. */
final class ReopenJobRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['deadline' => ['required', 'date_format:Y-m-d']];
    }

    public function deadline(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string('deadline')->toString(), new DateTimeZone('UTC'));
    }
}
