<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The author archives a test (it can no longer be sent; what already uses it keeps it) or restores it. Doing it twice changes nothing. Restoring a
 * company test needs room under the company's limit, because archived tests do not count.
 */
final readonly class ChangeTestArchive
{
    public function __construct(
        private AuthoringAccess $access,
        private TestRepository $tests,
        private Settings $settings,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-limit-reached`
     */
    public function execute(Actor $actor, int $testId, bool $archive): Test
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $testId, $archive): Test {
            $test = $this->access->ownedTest($author, $testId, true);

            if ($archive === $test->isArchived()) {
                return $test;
            }

            if ($archive) {
                $test->archive();
            } else {
                $companyId = $author->owner->companyId;

                if ($companyId !== null && $this->tests->countLiveOfCompany($companyId) >= $this->settings->integer(AssessmentSettings::MAX_COMPANY_TESTS, $companyId)) {
                    throw new StateConflict('The company has reached its limit of skill tests. Archive or delete one first.', 'test-limit-reached');
                }

                $test->restore();
            }

            return $this->tests->save($test);
        });
    }
}
