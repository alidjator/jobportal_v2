<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferAllowance;
use Src\Recruitment\Domain\OfferTerms;
use Src\Shared\Application\Company\CompanyLetterhead;

/**
 * Everything a generated offer letter shows, already in words: the renderer only lays it out. The language is the candidate's (`id` or `en`). A letter of an
 * offer without terms cannot be made (every new offer has them), so `of` expects the offer to carry its terms.
 */
final readonly class OfferLetterDocument
{
    private const string ZONE = 'Asia/Jakarta';

    /**
     * @param  array<string, string>  $labels
     * @param  list<array{label: string, value: string}>  $rows
     * @param  list<array{name: string, amount: string}>  $allowances
     * @param  list<string>  $benefits
     */
    public function __construct(
        public string $language,
        public array $labels,
        public string $companyName,
        public ?string $address,
        public ?string $logo,
        public string $date,
        public string $candidateName,
        public array $rows,
        public array $allowances,
        public ?string $allowancesTotal,
        public array $benefits,
        public ?string $note,
        public string $deadline,
        public string $issuedBy,
    ) {}

    public static function of(Offer $offer, OfferTerms $terms, ?CompanyLetterhead $letterhead, string $candidateName, string $language, string $senderName, string $platform, DateTimeImmutable $now): self
    {
        $labels = OfferLetterLabels::for($language);
        $language = $language === 'id' ? 'id' : 'en';

        $rows = [
            ['label' => $labels['position'], 'value' => $offer->position],
            ['label' => $labels['employment_type'], 'value' => $labels[$terms->employmentType->value]],
            ...($terms->contractMonths === null ? [] : [['label' => $labels['contract_length'], 'value' => $terms->contractMonths.' '.$labels['months']]]),
            ...($terms->probationMonths === null ? [] : [['label' => $labels['probation'], 'value' => $terms->probationMonths.' '.$labels['months']]]),
            ['label' => $labels['work_mode'], 'value' => $labels[$terms->workMode->value]],
            ...($terms->workLocation === null ? [] : [['label' => $labels['work_location'], 'value' => $terms->workLocation]]),
            ['label' => $labels['start_date'], 'value' => self::day($offer->startDate, $language)],
            ['label' => $labels['salary'], 'value' => self::money($offer->salary).' ('.$labels[$terms->salaryBasis->value].')'],
        ];

        return new self(
            $language,
            $labels,
            $letterhead instanceof CompanyLetterhead ? $letterhead->name : '',
            $letterhead?->address,
            $letterhead?->logoDataUri,
            self::day($now->setTimezone(new DateTimeZone(self::ZONE)), $language),
            $candidateName,
            $rows,
            array_map(static fn (OfferAllowance $line): array => ['name' => $line->name, 'amount' => self::money($line->amount)], $terms->allowances),
            $terms->allowances === [] ? null : self::money($terms->allowancesTotal()),
            array_map(static fn (array $benefit): string => $benefit['name'][$language], $terms->benefits),
            $offer->note,
            self::day($offer->expiresAt->setTimezone(new DateTimeZone(self::ZONE)), $language).' '.$offer->expiresAt->setTimezone(new DateTimeZone(self::ZONE))->format('H:i').' WIB',
            sprintf($labels['issued_by'], $senderName, $platform),
        );
    }

    private static function money(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private static function day(DateTimeImmutable $day, string $language): string
    {
        return $day->format('j').' '.OfferLetterLabels::months($language)[(int) $day->format('n') - 1].' '.$day->format('Y');
    }
}
