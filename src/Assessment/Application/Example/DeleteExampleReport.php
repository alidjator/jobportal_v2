<?php

namespace Src\Assessment\Application\Example;

use Psr\Log\LoggerInterface;
use Src\Assessment\Application\Authoring\AuthoringAccess;
use Src\Assessment\Domain\ExampleReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Throwable;

/** The psychologist takes the example report off a battery of their provider. A battery without one is not an error, so the call can be repeated. */
final readonly class DeleteExampleReport
{
    public function __construct(
        private AuthoringAccess $access,
        private ExampleReportRepository $examples,
        private FileStorage $files,
        private TransactionManager $transactions,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): void
    {
        $author = $this->access->author($actor);

        $example = $this->transactions->run(function () use ($author, $testId) {
            $this->access->ownedTest($author, $testId, true);
            $example = $this->examples->find($testId);
            $this->examples->delete($testId);

            return $example;
        });

        if ($example !== null) {
            try {
                $this->files->delete($example->path);
            } catch (Throwable $e) {
                $this->logger->warning('An example report file could not be deleted.', ['path' => $example->path, 'exception' => $e]);
            }
        }
    }
}
