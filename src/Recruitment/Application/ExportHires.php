<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Events\HiresExported;
use Src\Recruitment\Domain\OfferAllowance;
use Src\Recruitment\Domain\OfferTerms;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The hires of a period across the jobs the member may see (`docs/design/phase-8-hiring.md` section 3): every application now in `hired` whose last move into
 * `hired` falls in the Jakarta days `from` to `to`. At most `recruitment.max_export_rows` rows; the export is audited before it is handed out (the audit listener
 * is not queued, so when the entry cannot be written nothing is sent).
 */
final readonly class ExportHires
{
    private const int MAX_DAYS = 366;

    private const array HEADERS = [
        'Application ID', 'Hired at', 'Join date', 'Name', 'Email', 'Phone', 'City', 'Job ID', 'Job title',
        'Offer position', 'Offer salary (IDR)', 'Offer start date', 'Offer accepted at',
        'Employment type', 'Contract months', 'Probation months', 'Work mode', 'Work location', 'Salary basis', 'Allowances total (IDR)', 'Allowances', 'Benefits',
    ];

    public function __construct(
        private RecruitmentAccess $access,
        private HiringManagerScope $scope,
        private HireExportReader $reader,
        private JobDirectory $jobs,
        private EventDispatcher $events,
        private Clock $clock,
        private Settings $settings,
    ) {}

    /**
     * @param  string  $from  a Jakarta day, `YYYY-MM-DD`
     * @param  string  $to  a Jakarta day, `YYYY-MM-DD`, not before `$from` and at most 366 days after it (both days count)
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job of the filter
     * @throws BusinessRuleViolation `invalid-export-range` or `export-too-large`
     */
    public function execute(Actor $actor, string $from, string $to, ?int $jobId, ?string $ipAddress, ?string $userAgent): HiresExport
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsExport);
        $filter = $this->filter($member, $from, $to, $jobId);

        $cap = $this->settings->integer(RecruitmentSettings::MAX_EXPORT_ROWS, $member->companyId);
        $total = $this->reader->count($filter);

        if ($total > $cap) {
            throw new BusinessRuleViolation('An export can hold at most :max hires; narrow the period or the job.', 'export-too-large', extensions: ['max_rows' => $cap], parameters: ['max' => $cap]);
        }

        $this->events->dispatch([new HiresExported($member->companyId, $member->userId, $from, $to, $jobId, $total, $ipAddress, $userAgent, $this->clock->now())]);

        return new HiresExport($from, $to, self::HEADERS, $this->rows($filter, $total));
    }

    private function filter(RecruitmentMember $member, string $from, string $to, ?int $jobId): HireExportFilter
    {
        $zone = new DateTimeZone(StageClock::ZONE);
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone);
        $last = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone);

        if ($first === false || $last === false || $last < $first || (int) $first->diff($last)->days + 1 > self::MAX_DAYS) {
            throw new BusinessRuleViolation('The period must run from one day to a later or the same day, and last at most 366 days.', 'invalid-export-range');
        }

        if ($jobId !== null) {
            $this->access->job($member, $jobId);
        }

        $utc = new DateTimeZone('UTC');

        return new HireExportFilter(
            $member->companyId,
            $first->setTimezone($utc),
            $last->modify('+1 day')->setTimezone($utc),
            $jobId !== null ? [$jobId] : ($member->viewsAll() ? null : $this->jobs->assignedJobIds($member->companyId, $member->userId)),
            $this->scope->boundJobIds($member),
        );
    }

    /** @return iterable<list<string>> */
    private function rows(HireExportFilter $filter, int $limit): iterable
    {
        $titles = [];

        foreach ($this->reader->export($filter, $limit) as $hire) {
            $titles[$hire->jobId] ??= $this->jobs->titles([$hire->jobId])[$hire->jobId] ?? '';

            yield $this->cells($hire, $titles[$hire->jobId]);
        }
    }

    /** @return list<string> */
    private function cells(HireExportRow $hire, string $jobTitle): array
    {
        $offer = $hire->offer;

        return [
            (string) $hire->applicationId,
            $hire->hiredAt->format('Y-m-d\TH:i:s\Z'),
            $hire->joinDate?->format('Y-m-d') ?? '',
            // An anonymised applicant keeps a row for the count, with every personal column empty.
            $hire->isDeleted ? 'Deleted account' : (string) $hire->name,
            (string) $hire->email,
            (string) $hire->phone,
            (string) $hire->city,
            (string) $hire->jobId,
            $jobTitle,
            $offer === null ? '' : $offer->position,
            $offer === null ? '' : (string) $offer->salary,
            $offer === null ? '' : $offer->startDate->format('Y-m-d'),
            $offer === null ? '' : $offer->acceptedAt->format('Y-m-d\TH:i:s\Z'),
            ...$this->termCells($offer?->terms),
        ];
    }

    /**
     * The terms of the accepted offer; all empty for a hire without an accepted offer and for an offer sent before the terms existed. The allowances read
     * `Name: amount` and the benefits show their English names, both separated by `; `.
     *
     * @return list<string>
     */
    private function termCells(?OfferTerms $terms): array
    {
        if (! $terms instanceof OfferTerms) {
            return array_fill(0, 9, '');
        }

        return [
            $terms->employmentType->value,
            (string) $terms->contractMonths,
            (string) $terms->probationMonths,
            $terms->workMode->value,
            (string) $terms->workLocation,
            $terms->salaryBasis->value,
            (string) $terms->allowancesTotal(),
            implode('; ', array_map(static fn (OfferAllowance $line): string => $line->name.': '.$line->amount, $terms->allowances)),
            implode('; ', array_map(static fn (array $benefit): string => $benefit['name']['en'], $terms->benefits)),
        ];
    }
}
