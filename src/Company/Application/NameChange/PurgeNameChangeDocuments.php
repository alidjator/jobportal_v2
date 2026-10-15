<?php

namespace Src\Company\Application\NameChange;

use Psr\Log\LoggerInterface;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Throwable;

/**
 * Deletes the proof of a name change 90 days after the decision (approved, rejected or cancelled). The request stays, without the file. A file that cannot be
 * deleted is logged and tried again the next night.
 */
final readonly class PurgeNameChangeDocuments
{
    public const int RETENTION_DAYS = 90;

    private const int BATCH = 200;

    public function __construct(
        private NameChangeRequestRepository $requests,
        private FileStorage $files,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return int how many documents were deleted */
    public function execute(): int
    {
        $before = $this->clock->now()->modify('-'.self::RETENTION_DAYS.' days');
        $deleted = 0;

        do {
            $due = $this->requests->withDocumentDecidedBefore($before, self::BATCH);
            $progress = 0;

            foreach ($due as $request) {
                try {
                    $this->files->delete((string) $request->documentPath);
                    $this->requests->clearDocument((int) $request->id);
                    $progress++;
                } catch (Throwable $e) {
                    $this->logger->warning('A name change document could not be deleted.', ['request_id' => $request->id, 'exception' => $e]);
                }
            }

            $deleted += $progress;
        } while ($progress > 0 && count($due) === self::BATCH);

        return $deleted;
    }
}
