<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/** Daily housekeeping: deletes the pictures that were uploaded but that no question named within a day (the file first, then the row). */
final readonly class PurgeOrphanQuestionImages
{
    private const int BATCH = 200;

    public function __construct(
        private QuestionImageRepository $images,
        private FileStorage $files,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @return int how many pictures were deleted */
    public function execute(): int
    {
        $before = $this->clock->now()->modify('-1 day');
        $count = 0;

        while (($batch = $this->images->unattachedBefore($before, self::BATCH)) !== []) {
            foreach ($batch as $image) {
                $this->files->delete($image->path);
            }

            $this->transactions->run(fn () => $this->images->delete(array_map(static fn (QuestionImage $image): int => (int) $image->id, $batch)));
            $count += count($batch);
        }

        return $count;
    }
}
