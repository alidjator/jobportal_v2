<?php

namespace Src\Recruitment\Application\Checks;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Application\ApplicationFile;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A member with `checks.manage` reads the file attached to a result; every read is audited (`check.outcome_viewed`). */
final readonly class GetCheckResultFile
{
    public function __construct(
        private ChecksAccess $access,
        private ApplicationCheckRepository $checks,
        private CheckTypeRepository $types,
        private FileStorage $files,
        private AuditTrail $audit,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId, int $checkId, ?string $ipAddress = null, ?string $userAgent = null): ApplicationFile
    {
        $team = $this->access->team($actor, $applicationId, manage: true);
        $check = $this->checks->find($applicationId, $checkId) ?? throw EntityNotFound::of('Check', $checkId);

        if ($check->resultFilePath === null) {
            throw EntityNotFound::of('Check', $checkId);
        }

        $contents = $this->files->get($check->resultFilePath);

        if ($contents === null) {
            $this->logger->error('The file of a check result is missing.', ['check_id' => $checkId, 'path' => $check->resultFilePath]);

            throw EntityNotFound::of('Check', $checkId);
        }

        $kind = ($this->types->byIds([$check->checkTypeId])[$check->checkTypeId] ?? null)?->kind;
        $this->audit->record(new AuditEntry(
            AuditActorType::User, $team->member->userId, AuditAction::CHECK_OUTCOME_VIEWED, 'application_check', $checkId, ['application_id' => $applicationId, 'kind' => $kind?->value, 'file' => true],
            $ipAddress, $userAgent, $this->clock->now(), $team->member->companyId,
        ));

        return new ApplicationFile($contents, (string) $check->resultFileName);
    }
}
