<?php

namespace Src\Assessment\Application\Video;

use Src\Assessment\Domain\VideoTakeRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/** Daily housekeeping: deletes the video files whose retention period is over (file first, then the take is marked purged). The score and the answer stay. */
final readonly class PurgeExpiredVideos
{
    private const int BATCH = 200;

    public function __construct(
        private VideoTakeRepository $takes,
        private FileStorage $files,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @return int the number of files deleted */
    public function execute(): int
    {
        $now = $this->clock->now();
        $deleted = 0;

        while (($batch = $this->takes->expired($now, self::BATCH)) !== []) {
            foreach ($batch as $take) {
                $this->files->delete((string) $take->path);
                $this->transactions->run(fn () => $this->takes->update($take->purge()));
                $deleted++;
            }
        }

        return $deleted;
    }
}
