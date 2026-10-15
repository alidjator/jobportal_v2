<?php

namespace Src\JobSeeker\Application\Export;

use Src\JobSeeker\Domain\DataExportRepository;
use Src\JobSeeker\Domain\DataExportStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;

/** The daily cleanup: deletes the archives that passed their 24 hours (the request stays as `expired`) and gives up on builds that never finished. Safe to run again. */
final readonly class CleanUpDataExports
{
    private const int BATCH = 100;

    public function __construct(private DataExportRepository $exports, private FileStorage $files, private Clock $clock) {}

    /** @return int how many requests were closed */
    public function execute(): int
    {
        $closed = 0;

        while (($due = $this->exports->due($this->clock->now(), self::BATCH)) !== []) {
            foreach ($due as $export) {
                if ($export->status === DataExportStatus::Ready) {
                    if ($export->path !== null) {
                        $this->files->delete($export->path);
                    }

                    $this->exports->markExpired($export->id, $this->clock->now());
                } else {
                    $this->exports->markFailed($export->id, 'The build did not finish.', $this->clock->now());
                }

                $closed++;
            }
        }

        return $closed;
    }
}
