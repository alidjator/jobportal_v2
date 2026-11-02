<?php

namespace Src\Assessment\Application\Example;

use Src\Assessment\Application\Authoring\AuthoringAccess;
use Src\Assessment\Domain\ExampleReport;
use Src\Assessment\Domain\ExampleReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** What the psychologist's battery holds as its example report (the file name, its size and when it was uploaded), or null when it has none. The file itself goes to companies only. */
final readonly class GetExampleReport
{
    public function __construct(private AuthoringAccess $access, private ExampleReportRepository $examples) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): ?ExampleReport
    {
        $author = $this->access->author($actor);
        $this->access->ownedTest($author, $testId);

        return $this->examples->find($testId);
    }
}
