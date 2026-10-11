<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestRules;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author changes the title, description, instructions and measured capabilities of a test. */
final readonly class UpdateTest
{
    public function __construct(
        private AuthoringAccess $access,
        private TestRepository $tests,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<string>  $capabilities
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-test`
     * @throws StateConflict `test-archived` or `test-locked`
     */
    public function execute(Actor $actor, int $testId, string $title, ?string $description, ?string $instructions, array $capabilities): Test
    {
        $author = $this->access->author($actor);
        $title = TestRules::title($title);
        $description = TestRules::text('description', $description);
        $instructions = TestRules::text('instructions', $instructions);
        $capabilities = TestRules::capabilities($capabilities);

        return $this->transactions->run(function () use ($author, $testId, $title, $description, $instructions, $capabilities): Test {
            $test = $this->access->editableTest($author, $testId);
            $test->describe($title, $description, $instructions, $capabilities);

            return $this->tests->save($test);
        });
    }
}
