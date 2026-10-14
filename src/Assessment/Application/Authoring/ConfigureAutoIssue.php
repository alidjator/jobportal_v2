<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A psychologist turns the automatic issue of reports on or off for a battery of their provider and names the responsible psychologist (an active psychologist of the
 * provider). It is a policy, not content, so it can change after the battery was sent to candidates. Turning it on needs the platform admin's permission for the provider;
 * on a published battery everything a report needs is checked at once (`test-incomplete` lists what is missing), on a draft it is checked when the battery is published.
 */
final readonly class ConfigureAutoIssue
{
    public function __construct(
        private AuthoringAccess $access,
        private TestRepository $tests,
        private ProviderRepository $providers,
        private PsychologistRepository $psychologists,
        private AutoIssueChecks $checks,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived`
     * @throws BusinessRuleViolation `test-not-psychological`, `auto-issue-not-allowed`, `invalid-psychologist` or `test-incomplete`
     */
    public function execute(Actor $actor, int $testId, bool $enabled, ?int $responsiblePsychologistId): Test
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $testId, $enabled, $responsiblePsychologistId): Test {
            $test = $this->access->ownedTest($author, $testId, true);

            if ($test->kind() !== TestKind::Psychological) {
                throw new BusinessRuleViolation('Only a psychological battery issues reports.', 'test-not-psychological');
            }

            if ($enabled) {
                $this->assertMayEnable($test, $responsiblePsychologistId);
            }

            $test->configureAutoIssue($enabled, $responsiblePsychologistId);

            if ($enabled && $test->isPublished()) {
                $problems = $this->checks->problems($test);

                if ($problems !== []) {
                    throw new BusinessRuleViolation('The battery cannot issue reports automatically yet.', 'test-incomplete', extensions: ['errors' => ['test' => $problems]]);
                }
            }

            return $this->tests->save($test);
        });
    }

    /** @throws BusinessRuleViolation `auto-issue-not-allowed` or `invalid-psychologist` */
    private function assertMayEnable(Test $test, ?int $responsibleId): void
    {
        $provider = $test->owner->providerId === null ? null : $this->providers->findById($test->owner->providerId);

        if ($provider === null || ! $provider->autoIssueAllowed()) {
            throw new BusinessRuleViolation('The provider is not allowed to issue reports automatically. A platform admin allows it after review.', 'auto-issue-not-allowed');
        }

        $responsible = $responsibleId === null ? null : $this->psychologists->findById($responsibleId);

        if ($responsible === null || $responsible->providerId !== $test->owner->providerId || ! $responsible->isActive()) {
            $message = 'Name an active psychologist of the provider as the responsible psychologist.';

            throw new BusinessRuleViolation($message, 'invalid-psychologist', extensions: ['errors' => ['responsible_psychologist_id' => [$message]]]);
        }
    }
}
