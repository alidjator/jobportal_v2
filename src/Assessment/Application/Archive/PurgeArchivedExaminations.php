<?php

namespace Src\Assessment\Application\Archive;

use Src\Assessment\Domain\ExaminationArchiveRepository;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Daily housekeeping: deletes the archived examinations whose retention ended (the files first, then the row) and audits the counts. It runs by date whatever the switch says: switching the retention off
 * never deletes what is archived, and never keeps it past its day (`docs/design/phase-8-provider-institutions.md` section 7).
 */
final readonly class PurgeArchivedExaminations
{
    private const int BATCH = 200;

    public function __construct(
        private ExaminationArchiveRepository $archive,
        private FileStorage $files,
        private TransactionManager $transactions,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /** @return array{examinations: int, files: int} */
    public function execute(): array
    {
        $now = $this->clock->now();
        $examinations = 0;
        $files = 0;

        while (($batch = $this->archive->endedBefore($now, self::BATCH)) !== []) {
            foreach ($batch as $examination) {
                foreach ($examination->files as $file) {
                    $this->files->delete($file['path']);
                    $files++;
                }

                $this->transactions->run(fn () => $this->archive->remove((int) $examination->id));
                $examinations++;
            }
        }

        if ($examinations > 0) {
            $this->audit->record(new AuditEntry(AuditActorType::System, null, AuditAction::ASSESSMENT_ARCHIVE_PURGED, null, null, ['examinations' => $examinations, 'files' => $files], null, null, $now));
        }

        return ['examinations' => $examinations, 'files' => $files];
    }
}
