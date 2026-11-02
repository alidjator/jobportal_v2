<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\ExampleReportRepository;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author deletes a draft. A test that was published is archived instead, because invitations may refer to it. */
final readonly class DeleteTest
{
    public function __construct(
        private AuthoringAccess $access,
        private TestRepository $tests,
        private ExampleReportRepository $examples,
        private FileStorage $files,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-not-draft`
     */
    public function execute(Actor $actor, int $testId): void
    {
        $author = $this->access->author($actor);

        $example = $this->transactions->run(function () use ($author, $testId) {
            $test = $this->access->ownedTest($author, $testId, true);

            if (! $test->isDraft()) {
                throw new StateConflict('Only a draft can be deleted. Archive the test instead.', 'test-not-draft');
            }

            $example = $this->examples->find($testId);
            $this->tests->delete($testId);

            return $example;
        });

        // The row of the example report went with the draft; its file goes too.
        if ($example !== null) {
            $this->files->delete($example->path);
        }
    }
}
