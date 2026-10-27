<?php

namespace Src\Assessment\Application\Register;

use DateTimeImmutable;
use DateTimeZone;
use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\Events\ReportRegisterExported;
use Src\Assessment\Domain\RegisterEntry;
use Src\Assessment\Domain\RegisterFilter;
use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The report register of the psychologist's provider as a CSV file, for at most 366 days and `MAX_ROWS` lines (the rules of `docs/design/phase-2-export.md`: streamed, throttled by the route, audited). It answers the duty to keep a record of the
 * reports. The file has no candidate. The export is audited before it is handed out.
 */
final readonly class ExportReportRegister
{
    public const int MAX_DAYS = 366;

    public const int MAX_ROWS = 50000;

    private const array HEADERS = ['Report number', 'Revision', 'Signed at', 'Signed by', 'Battery', 'Company', 'Issued automatically', 'Reviewed', 'Recommendation', 'Candidate data erased'];

    public function __construct(
        private ReviewAccess $access,
        private ReportRepository $reports,
        private RegisterItems $items,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws BusinessRuleViolation `register-range-required`, `register-range-too-long` or `export-too-large`
     */
    public function execute(Actor $actor, RegisterFilter $filter, ?string $ipAddress, ?string $userAgent): RegisterExport
    {
        $psychologist = $this->access->psychologist($actor);

        if ($filter->from === null || $filter->to === null) {
            throw new BusinessRuleViolation('Give the first and the last day of the export.', 'register-range-required');
        }

        if (AnalyticsDays::count($filter->from, $filter->to) > self::MAX_DAYS) {
            throw new BusinessRuleViolation('An export can span at most :max days.', 'register-range-too-long', extensions: ['max_days' => self::MAX_DAYS], parameters: ['max' => self::MAX_DAYS]);
        }

        $total = $this->reports->registerCount($psychologist->providerId, $filter);

        if ($total > self::MAX_ROWS) {
            throw new BusinessRuleViolation('An export can hold at most :max reports; narrow the filters.', 'export-too-large', extensions: ['max_rows' => self::MAX_ROWS], parameters: ['max' => self::MAX_ROWS]);
        }

        $this->events->dispatch([new ReportRegisterExported(
            $psychologist->providerId, (int) $actor->id,
            ['from' => $filter->from, 'to' => $filter->to, 'psychologist_id' => $filter->psychologistId, 'test_id' => $filter->testId],
            $total, $ipAddress, $userAgent, $this->clock->now(),
        )]);

        return new RegisterExport("report-register-{$filter->from}-{$filter->to}.csv", self::HEADERS, $this->rows($psychologist->providerId, $filter, $total));
    }

    /** @return iterable<list<string>> */
    private function rows(int $providerId, RegisterFilter $filter, int $limit): iterable
    {
        $chunk = [];

        foreach ($this->reports->registerRows($providerId, $filter, $limit) as $entry) {
            $chunk[] = $entry;

            if (count($chunk) === 200) {
                yield from $this->cells($chunk);
                $chunk = [];
            }
        }

        yield from $this->cells($chunk);
    }

    /**
     * @param  list<RegisterEntry>  $entries
     * @return iterable<list<string>>
     */
    private function cells(array $entries): iterable
    {
        $names = $this->items->companyNames($entries);

        foreach ($entries as $entry) {
            yield [
                $entry->number,
                $entry->revision > 0 ? 'R'.$entry->revision : '',
                $entry->signedAt->setTimezone(new DateTimeZone('UTC'))->format(DateTimeImmutable::ATOM),
                (string) $entry->signerName,
                $entry->testTitle,
                $entry->companyId === null ? '' : ($names[$entry->companyId] ?? ''),
                $entry->autoIssued ? 'yes' : 'no',
                $entry->reviewed() ? 'yes' : 'no',
                $entry->recommendation->value ?? '',
                $entry->erased ? 'yes' : 'no',
            ];
        }
    }
}
