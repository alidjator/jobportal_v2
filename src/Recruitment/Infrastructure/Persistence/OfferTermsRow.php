<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Src\Recruitment\Domain\EmploymentType;
use Src\Recruitment\Domain\OfferAllowance;
use Src\Recruitment\Domain\OfferTerms;
use Src\Recruitment\Domain\OfferWorkMode;
use Src\Recruitment\Domain\SalaryBasis;
use stdClass;

/** Reads the terms of an offer from a row of `recruitment_offers` (the offer repository and the hires export read them the same way). */
final class OfferTermsRow
{
    /** The columns {@see self::terms()} reads. */
    public const array COLUMNS = ['employment_type', 'contract_months', 'probation_months', 'work_mode', 'work_location', 'salary_basis', 'allowances', 'benefits'];

    /** The terms of an offer, or null for an offer sent before they existed. */
    public static function terms(stdClass $row): ?OfferTerms
    {
        if ($row->employment_type === null) {
            return null;
        }

        /** @var list<array{name: string, amount: int}> $allowances */
        $allowances = json_decode((string) ($row->allowances ?? '[]'), true, flags: JSON_THROW_ON_ERROR);
        /** @var list<array{id: int, name: array{id: string, en: string}}> $benefits */
        $benefits = json_decode((string) ($row->benefits ?? '[]'), true, flags: JSON_THROW_ON_ERROR);

        return new OfferTerms(
            EmploymentType::from((string) $row->employment_type),
            $row->contract_months === null ? null : (int) $row->contract_months,
            $row->probation_months === null ? null : (int) $row->probation_months,
            OfferWorkMode::from((string) $row->work_mode),
            $row->work_location === null ? null : (string) $row->work_location,
            SalaryBasis::from((string) $row->salary_basis),
            array_map(static fn (array $line): OfferAllowance => new OfferAllowance((string) $line['name'], (int) $line['amount']), $allowances),
            $benefits,
        );
    }
}
