<?php

namespace Src\Recruitment\Application\Checks;

use DateTimeImmutable;
use Src\Recruitment\Application\BulkItemResult;
use Src\Recruitment\Application\RecruitmentAccess;
use Src\Recruitment\Application\RecruitmentMember;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ErrorMessage;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member with `checks.manage` requests the same check on several applications of one job (`docs/design/phase-8-pre-employment-checks.md` section 2). The request is checked as a whole first (selection, check type, shared
 * input), and a mistake there changes nothing. Then every application goes through {@see RequestCheck} on its own, so each keeps the rules, notification and transaction of the single request, and one that cannot be
 * processed (not in an interview, offer or hired stage, a check of the type already open) is reported and skipped without touching the others.
 */
final readonly class BulkRequestChecks
{
    public function __construct(
        private ChecksAccess $checksAccess,
        private RecruitmentAccess $access,
        private RequestCheck $request,
        private ApplicationRepository $applications,
        private CheckTypeRepository $types,
        private CandidateMessageRules $messageRules,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @param  list<int>  $applicationIds
     * @return list<BulkItemResult> in the order of the selection; `moved` here means the check was requested
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound the member may not see the job, or the check type
     * @throws BusinessRuleViolation `invalid-applications`, `too-many-applications`, `check-type-inactive`, `invalid-check` or `message-link-not-allowed`
     */
    public function execute(Actor $actor, int $jobId, array $applicationIds, int $checkTypeId, ?DateTimeImmutable $scheduledAt, ?string $place, ?string $vendor, ?string $noteToCandidate): array
    {
        $member = $this->checksAccess->member($actor);
        $this->access->job($member, $jobId);

        $this->assertSelection($member, $jobId, $applicationIds);
        $type = $this->types->find($member->companyId, $checkTypeId) ?? throw EntityNotFound::of('Check type', $checkTypeId);

        if (! $type->isActive) {
            throw new BusinessRuleViolation('This check type is not in use any more.', 'check-type-inactive');
        }

        $this->messageRules->assertAllowed($member->companyId, $noteToCandidate, 'note_to_candidate');
        ApplicationCheck::request(0, $checkTypeId, $type->requiresConsent, $scheduledAt, $place, $vendor, $noteToCandidate, $member->userId, $this->clock->now());

        return array_map(fn (int $id): BulkItemResult => $this->requestOne($actor, $id, $checkTypeId, $scheduledAt, $place, $vendor, $noteToCandidate), $applicationIds);
    }

    private function requestOne(Actor $actor, int $applicationId, int $checkTypeId, ?DateTimeImmutable $scheduledAt, ?string $place, ?string $vendor, ?string $noteToCandidate): BulkItemResult
    {
        try {
            $this->request->execute($actor, $applicationId, $checkTypeId, $scheduledAt, $place, $vendor, $noteToCandidate);
        } catch (StateConflict|BusinessRuleViolation $error) {
            return new BulkItemResult($applicationId, $error->errorCode());
        } catch (EntityNotFound) {
            return new BulkItemResult($applicationId, 'application-not-found');
        }

        return new BulkItemResult($applicationId);
    }

    /**
     * Every id is an application of this job in the member's company, and the selection is within the cap.
     *
     * @param  list<int>  $applicationIds
     *
     * @throws BusinessRuleViolation `invalid-applications` or `too-many-applications`
     */
    private function assertSelection(RecruitmentMember $member, int $jobId, array $applicationIds): void
    {
        $cap = $this->settings->integer(RecruitmentSettings::MAX_BULK_APPLICATIONS, $member->companyId);

        if (count($applicationIds) > $cap) {
            $message = new ErrorMessage('Select at most :max applications at a time.', ['max' => $cap]);

            throw new BusinessRuleViolation($message, 'too-many-applications', extensions: ['errors' => ['application_ids' => [$message]]]);
        }

        foreach ($applicationIds as $id) {
            $state = $this->applications->find($id);

            if (! $state instanceof Application || $state->jobId !== $jobId || $state->companyId !== $member->companyId) {
                $message = 'Select applications of this job only.';

                throw new BusinessRuleViolation($message, 'invalid-applications', extensions: ['errors' => ['application_ids' => [$message]]]);
            }
        }
    }
}
