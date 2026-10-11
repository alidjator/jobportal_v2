<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
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

        $this->transactions->run(function () use ($author, $testId): void {
            $test = $this->access->ownedTest($author, $testId, true);

            if (! $test->isDraft()) {
                throw new StateConflict('Only a draft can be deleted. Archive the test instead.', 'test-not-draft');
            }

            $this->tests->delete($testId);
        });
    }
}
