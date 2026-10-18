<?php

namespace Src\Recruitment\Application;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\OfferLetterUpload;
use Src\Recruitment\Domain\OfferLetterUploadRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Throwable;

/** Deletes the letter uploads that no offer used within {@see OfferLetterUpload::VALID_HOURS} hours, file first and row after (a failure leaves the row for the next run). */
final readonly class PurgeOfferLetterUploads
{
    private const int BATCH = 200;

    public function __construct(
        private OfferLetterUploadRepository $uploads,
        private FileStorage $files,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return int how many uploads were deleted */
    public function execute(): int
    {
        $cutoff = $this->clock->now()->modify('-'.OfferLetterUpload::VALID_HOURS.' hours');
        $deleted = 0;

        do {
            $batch = $this->uploads->createdBefore($cutoff, self::BATCH);
            $progress = 0;

            foreach ($batch as $upload) {
                try {
                    $this->files->delete($upload->path);
                } catch (Throwable $e) {
                    $this->logger->warning('An expired offer letter upload could not be deleted.', ['upload_id' => $upload->id, 'exception' => $e]);

                    continue;
                }

                $this->uploads->delete((int) $upload->id);
                $progress++;
            }

            $deleted += $progress;
        } while ($progress > 0 && count($batch) === self::BATCH);

        return $deleted;
    }
}
