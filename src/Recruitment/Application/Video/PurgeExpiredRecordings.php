<?php

namespace Src\Recruitment\Application\Video;

use DateInterval;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;

/** Daily housekeeping: deletes the recordings whose retention period is over (file first, then row) and gives up on recordings that never finished converting. */
final readonly class PurgeExpiredRecordings
{
    /** A conversion that has not reported back after this many hours will not. */
    private const int STUCK_AFTER_HOURS = 6;

    public function __construct(private RecordingRepository $recordings, private RecordingPipeline $pipeline, private FileStorage $files, private Clock $clock) {}

    /** @return int the number of recordings deleted */
    public function execute(): int
    {
        $now = $this->clock->now();

        foreach ($this->recordings->stuck($now->sub(new DateInterval('PT'.self::STUCK_AFTER_HOURS.'H'))) as $stuck) {
            $this->pipeline->fail((int) $stuck->id);
        }

        $deleted = 0;

        foreach ($this->recordings->expired($now) as $recording) {
            if ($recording->path !== null) {
                $this->files->delete($recording->path);
            }

            $this->recordings->delete((int) $recording->id);
            $deleted++;
        }

        return $deleted;
    }
}
