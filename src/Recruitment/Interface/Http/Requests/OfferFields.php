<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\OfferInput;
use Src\Recruitment\Application\OfferTermsInput;
use Src\Recruitment\Domain\EmploymentType;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferAllowance;
use Src\Recruitment\Domain\OfferWorkMode;
use Src\Recruitment\Domain\SalaryBasis;

/**
 * The offer fields shared by the send request and the move request (where they sit under `offer`). The deadline is ISO 8601 with an offset or `Z`, the start
 * date a plain day. Ranges and dates are domain rules (422 `invalid-offer`); a position left out defaults to the job title. The terms (employment type, contract
 * and probation months, work mode and location, salary basis, allowances, benefit ids) are part of every offer; `letter_upload_id` names the company's own letter.
 */
final class OfferFields
{
    private const string TIME_FORMAT = 'Y-m-d\TH:i:sp';

    public const int BENEFITS_MAX = 50;

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = '', string $presence = 'required'): array
    {
        return [
            $prefix.'position' => ['nullable', 'string', 'max:'.Offer::POSITION_MAX],
            $prefix.'salary' => [$presence, 'integer'],
            $prefix.'start_date' => [$presence, 'date_format:Y-m-d'],
            $prefix.'note' => ['nullable', 'string', 'max:'.Offer::NOTE_MAX],
            $prefix.'expires_at' => [$presence, 'date_format:'.self::TIME_FORMAT],
            $prefix.'employment_type' => [$presence, Rule::enum(EmploymentType::class)],
            $prefix.'contract_months' => ['nullable', 'integer'],
            $prefix.'probation_months' => ['nullable', 'integer'],
            $prefix.'work_mode' => [$presence, Rule::enum(OfferWorkMode::class)],
            $prefix.'work_location' => ['nullable', 'string'],
            $prefix.'salary_basis' => [$presence, Rule::enum(SalaryBasis::class)],
            $prefix.'allowances' => ['nullable', 'array'],
            // `$presence` rather than a plain `required`: the offer is optional in a move, and a plain `required` here would mark the whole `offer` object required in the API document.
            $prefix.'allowances.*.name' => [$presence, 'string'],
            $prefix.'allowances.*.amount' => [$presence, 'integer'],
            $prefix.'benefit_ids' => ['nullable', 'array', 'max:'.self::BENEFITS_MAX],
            $prefix.'benefit_ids.*' => ['integer'],
            $prefix.'letter_upload_id' => ['nullable', 'integer'],
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function input(array $data): OfferInput
    {
        $expires = (string) $data['expires_at'];

        return new OfferInput(
            isset($data['position']) ? (string) $data['position'] : null,
            (int) $data['salary'],
            new DateTimeImmutable((string) $data['start_date'], new DateTimeZone('UTC')),
            isset($data['note']) ? (string) $data['note'] : null,
            DateTimeImmutable::createFromFormat(self::TIME_FORMAT, $expires) ?: new DateTimeImmutable($expires),
            new OfferTermsInput(
                EmploymentType::from((string) $data['employment_type']),
                isset($data['contract_months']) ? (int) $data['contract_months'] : null,
                isset($data['probation_months']) ? (int) $data['probation_months'] : null,
                OfferWorkMode::from((string) $data['work_mode']),
                isset($data['work_location']) ? (string) $data['work_location'] : null,
                SalaryBasis::from((string) $data['salary_basis']),
                array_map(static fn (array $line): OfferAllowance => new OfferAllowance((string) $line['name'], (int) $line['amount']), array_values($data['allowances'] ?? [])),
                array_map('intval', array_values($data['benefit_ids'] ?? [])),
            ),
            isset($data['letter_upload_id']) ? (int) $data['letter_upload_id'] : null,
        );
    }
}
