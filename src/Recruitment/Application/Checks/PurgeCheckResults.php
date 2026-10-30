<?php

namespace Src\Recruitment\Application\Checks;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckKind;
use Src\Recruitment\Domain\CheckType;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Throwable;

/**
 * Applies the retention of check results (`docs/design/phase-8-pre-employment-checks.md` section 5): {@see ApplicationCheck::RETENTION_DAYS} days after an application entered a final stage the outcome and the note of a medical
 * check go, and the file of every check. The status and the fact that the check was done stay. The file is deleted first and the row changed after it (a failure leaves the check for the next run).
 */
final readonly class PurgeCheckResults
{
    private const int BATCH = 200;

    public function __construct(
        private ApplicationCheckRepository $checks,
        private CheckTypeRepository $types,
        private FileStorage $files,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return int how many checks lost their result */
    public function execute(): int
    {
        $now = $this->clock->now();
        $cutoff = $now->modify('-'.ApplicationCheck::RETENTION_DAYS.' days');
        $purged = 0;

        do {
            $batch = $this->checks->dueForPurge($cutoff, self::BATCH);
            $kinds = array_map(static fn (CheckType $type): CheckKind => $type->kind, $this->types->byIds(array_values(array_unique(array_map(static fn (ApplicationCheck $check): int => $check->checkTypeId, $batch)))));
            $progress = 0;

            foreach ($batch as $check) {
                if ($check->resultFilePath !== null && ! $this->deleteFile($check)) {
                    continue;
                }

                $this->checks->save(($kinds[$check->checkTypeId] ?? null) === CheckKind::Medical ? $check->withoutResult($now) : $check->withoutFile($now));
                $progress++;
            }

            $purged += $progress;
        } while ($progress > 0 && count($batch) === self::BATCH);

        return $purged;
    }

    private function deleteFile(ApplicationCheck $check): bool
    {
        try {
            $this->files->delete((string) $check->resultFilePath);

            return true;
        } catch (Throwable $e) {
            $this->logger->warning('The result file of a check could not be deleted.', ['check_id' => $check->id, 'exception' => $e]);

            return false;
        }
    }
}
