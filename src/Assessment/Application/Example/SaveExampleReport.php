<?php

namespace Src\Assessment\Application\Example;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Src\Assessment\Application\Authoring\AuthoringAccess;
use Src\Assessment\Domain\ExampleReport;
use Src\Assessment\Domain\ExampleReportRepository;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Throwable;

/**
 * A psychologist attaches an example report (a PDF about a fictitious candidate) to a battery of their provider, or replaces the one it has. It is marketing, not content, so a battery that
 * candidates were invited to can still change it. The file is stored first and the row saved next; a failure removes the new file, and the replaced file goes after the row.
 */
final readonly class SaveExampleReport
{
    private const string DIRECTORY = 'assessment/examples';

    public function __construct(
        private AuthoringAccess $access,
        private ExampleReportRepository $examples,
        private FileStorage $files,
        private TransactionManager $transactions,
        private LoggerInterface $logger,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `test-not-psychological`, `file-too-large` or `invalid-example-report`
     */
    public function execute(Actor $actor, int $testId, string $contents, string $originalName): ExampleReport
    {
        $author = $this->access->author($actor);

        ExampleReportDocument::assertValid($contents);

        $path = self::DIRECTORY.'/'.$testId.'/'.bin2hex(random_bytes(16)).'.pdf';
        $this->files->put($path, $contents);

        try {
            [$example, $replaced] = $this->transactions->run(function () use ($author, $testId, $path, $originalName, $contents): array {
                $test = $this->access->ownedTest($author, $testId, true);

                if ($test->kind() !== TestKind::Psychological) {
                    throw new BusinessRuleViolation('Only a psychological battery has an example report.', 'test-not-psychological');
                }

                $replaced = $this->examples->find($testId);
                $example = new ExampleReport($path, ExampleReportDocument::cleanName($originalName), strlen($contents), $this->now());
                $this->examples->save($testId, $example);

                return [$example, $replaced];
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        if ($replaced !== null) {
            $this->deleteQuietly($replaced->path);
        }

        return $example;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('An example report file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
