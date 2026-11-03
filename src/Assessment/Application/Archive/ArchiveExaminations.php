<?php

namespace Src\Assessment\Application\Archive;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\ArchivedExamination;
use Src\Assessment\Domain\ExaminationArchiveRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;

/**
 * The first half of the erasure of a candidate's account when the retention switch is on (`docs/design/phase-8-provider-institutions.md` section 7): for each psychological attempt of the user that is not archived
 * yet, the files are copied to the archive folder and the archive row is added with the raw data and the day it may be purged. The caller deletes the live data afterwards. A copy that fails throws before any
 * row is added, so nothing is deleted and the retry starts again; an attempt that is archived is skipped, so a retry never archives twice. A live file that is already gone cannot be kept and is left out.
 */
final readonly class ArchiveExaminations
{
    public function __construct(
        private ExaminationArchiveRepository $archive,
        private FileStorage $files,
        private TransactionManager $transactions,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /** @return int the number of attempts archived (0 while the switch is off) */
    public function execute(int $userId): int
    {
        if (! $this->settings->boolean(AssessmentSettings::RAW_DATA_RETENTION_ENABLED)) {
            return 0;
        }

        $now = $this->clock->now();
        $retainUntil = $now->modify('+'.$this->settings->integer(AssessmentSettings::RAW_DATA_RETENTION_YEARS).' years');
        $archived = 0;

        foreach ($this->archive->snapshotsOf($userId) as $snapshot) {
            $files = [];

            foreach ($snapshot->sources as $index => $source) {
                $contents = $this->files->get($source['path']);

                if ($contents === null) {
                    continue;
                }

                $path = sprintf('assessment/archive/%d/%d/%d', $snapshot->providerId, $snapshot->attemptId, $index);
                $this->files->put($path, $contents);
                $files[] = ['n' => $index, 'kind' => $source['kind'], 'name' => $source['name'], 'path' => $path, 'sha256' => hash('sha256', $contents)];
            }

            $this->transactions->run(fn () => $this->archive->add(new ArchivedExamination(
                null, $snapshot->attemptId, $snapshot->providerId, $snapshot->reportNumber, $now, $retainUntil, $snapshot->payload, $files,
            )));
            $archived++;
        }

        return $archived;
    }
}
