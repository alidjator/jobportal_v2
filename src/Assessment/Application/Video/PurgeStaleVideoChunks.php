<?php

namespace Src\Assessment\Application\Video;

use Src\Assessment\Domain\VideoTakeRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;

/** Hourly housekeeping: a take still `recording` a day after it started will never finish, so its chunks are deleted and the take is rejected. */
final readonly class PurgeStaleVideoChunks
{
    private const int BATCH = 200;

    public function __construct(
        private VideoTakeRepository $takes,
        private FileStorage $files,
        private Clock $clock,
    ) {}

    /** @return int the number of takes given up */
    public function execute(): int
    {
        $now = $this->clock->now();
        $count = 0;

        while (($batch = $this->takes->stale($now->modify('-1 day'), self::BATCH)) !== []) {
            foreach ($batch as $take) {
                for ($index = 0; $index < $take->chunkCount; $index++) {
                    $this->files->delete(VideoTakes::chunkPath((int) $take->id, $index));
                }

                $this->takes->update($take->reject($now));
                $count++;
            }
        }

        return $count;
    }
}
