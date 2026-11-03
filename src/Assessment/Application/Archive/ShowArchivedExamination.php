<?php

namespace Src\Assessment\Application\Archive;

use Src\Assessment\Domain\ArchivedExamination;
use Src\Assessment\Domain\ExaminationArchiveRepository;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Reads the archived raw data of a report number for an operator on a documented legal request. The request reference is required and goes into the audit entry with the report number
 * (`docs/design/phase-8-provider-institutions.md` section 7); nothing in the portal or the Console reads the archive.
 */
final readonly class ShowArchivedExamination
{
    public function __construct(
        private ExaminationArchiveRepository $archive,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @return list<ArchivedExamination>
     *
     * @throws EntityNotFound when nothing is archived under the number
     */
    public function execute(string $reportNumber, string $reference): array
    {
        $found = $this->archive->findByReportNumber($reportNumber);

        if ($found === []) {
            throw EntityNotFound::of('ArchivedExamination', $reportNumber);
        }

        foreach ($found as $examination) {
            $this->audit->record(new AuditEntry(
                AuditActorType::System, null, AuditAction::ASSESSMENT_ARCHIVE_READ, 'assessment_archived_examination', $examination->id,
                ['report_number' => $reportNumber, 'reference' => mb_substr($reference, 0, 200)], null, null, $this->clock->now(),
            ));
        }

        return $found;
    }
}
