<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestRules;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin, a psychologist or an admin starts a new test as a draft: a skill test of the company, a battery of the provider or a test of the library. */
final readonly class CreateTest
{
    public function __construct(
        private AuthoringAccess $access,
        private TestRepository $tests,
        private Settings $settings,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<string>  $capabilities
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws BusinessRuleViolation `invalid-test`
     * @throws StateConflict `test-limit-reached`
     */
    public function execute(Actor $actor, string $title, ?string $description, ?string $instructions, array $capabilities): Test
    {
        $author = $this->access->author($actor);

        $test = Test::draft(
            $author->owner,
            TestRules::title($title),
            TestRules::text('description', $description),
            TestRules::text('instructions', $instructions),
            TestRules::capabilities($capabilities),
            $author->userId,
            $author->adminId,
        );

        return $this->transactions->run(function () use ($author, $test): Test {
            if ($author->owner->companyId !== null) {
                $this->assertRoom($author->owner->companyId);
            }

            return $this->tests->save($test);
        });
    }

    private function assertRoom(int $companyId): void
    {
        if ($this->tests->countLiveOfCompany($companyId) >= $this->settings->integer(AssessmentSettings::MAX_COMPANY_TESTS, $companyId)) {
            throw new StateConflict('The company has reached its limit of skill tests. Archive or delete one first.', 'test-limit-reached');
        }
    }
}
