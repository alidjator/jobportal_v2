<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\MessageReportRepository;
use Src\Messaging\Domain\MessagingDataEraser;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Erases what chat holds of a user whose account was anonymised: their files first, then the file rows and the text of their messages, and what the chat reports hold of them (reporter, note, message snapshots), in one transaction. A
 * failure to delete a file stops everything before any row is cleared, so a retry still knows which files to delete; running it again after success does nothing.
 */
final readonly class EraseMessagingData implements ErasureStep
{
    public function __construct(private MessagingDataEraser $eraser, private MessageReportRepository $reports, private FileStorage $files, private TransactionManager $transactions) {}

    public function name(): string
    {
        return 'messaging-data';
    }

    public function execute(int $userId): void
    {
        foreach ($this->eraser->attachmentPaths($userId) as $path) {
            $this->files->delete($path);
        }

        $this->transactions->run(function () use ($userId): void {
            $this->eraser->clearPersonalData($userId);
            $this->reports->eraseUser($userId);
        });
    }
}
