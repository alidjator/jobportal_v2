<?php

namespace Src\Assessment\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/** The licence fields shared by inviting a psychologist and renewing their credentials. */
abstract class PsychologistCredentialsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    protected function credentialRules(): array
    {
        return [
            'str_number' => ['required', 'string', 'min:3', 'max:60'],
            'str_valid_until' => ['required', 'date_format:Y-m-d'],
            'silp_number' => ['required', 'string', 'min:3', 'max:60'],
            'silp_valid_until' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function strNumber(): string
    {
        return $this->string('str_number')->toString();
    }

    public function strValidUntil(): DateTimeImmutable
    {
        return $this->day('str_valid_until');
    }

    public function silpNumber(): string
    {
        return $this->string('silp_number')->toString();
    }

    public function silpValidUntil(): DateTimeImmutable
    {
        return $this->day('silp_valid_until');
    }

    private function day(string $key): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string($key)->toString(), new DateTimeZone('UTC'));
    }
}
