<?php

namespace Src\Recruitment\Application\Checks;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckOutcome;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A member with `checks.manage` records the result of a check, which completes it. A medical check keeps a category only (fit, fit with notes, unfit) and no file; the others may carry a PDF.
 * Nothing moves by itself: an unfit or failed check rejects and withdraws nothing. The audit entry holds no note text.
 */
final readonly class RecordCheckResult
{
    private const string DIRECTORY = 'recruitment/check-results';

    public function __construct(
        private ChecksAccess $access,
        private ApplicationCheckRepository $checks,
        private CheckTypeRepository $types,
        private FileStorage $files,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-check` or `invalid-file`
     * @throws StateConflict `check-closed` or `check-consent-pending`
     */
    public function execute(
        Actor $actor, int $applicationId, int $checkId, CheckOutcome $outcome, ?string $note, ?string $fileContents, ?string $fileName, ?string $ipAddress = null, ?string $userAgent = null,
    ): ApplicationCheck {
        $path = null;

        try {
            return $this->transactions->run(function () use ($actor, $applicationId, $checkId, $outcome, $note, $fileContents, $fileName, $ipAddress, $userAgent, &$path): ApplicationCheck {
                $team = $this->access->team($actor, $applicationId, manage: true, forUpdate: true);
                $check = $this->checks->find($applicationId, $checkId, forUpdate: true) ?? throw EntityNotFound::of('Check', $checkId);
                $type = $this->types->byIds([$check->checkTypeId])[$check->checkTypeId] ?? throw EntityNotFound::of('Check', $checkId);

                if ($fileContents !== null) {
                    if (! $type->kind->acceptsFile()) {
                        throw new BusinessRuleViolation('A medical check keeps a category only, never a file.', 'invalid-check', extensions: ['errors' => ['file' => ['A medical check keeps a category only, never a file.']]]);
                    }

                    CheckResultFile::check($fileContents);
                    $path = self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.pdf';
                }

                $now = $this->clock->now();
                $done = $check->completed($outcome, $note, $path, $path === null ? null : CheckResultFile::cleanName((string) $fileName), $team->member->userId, $now);

                if ($path !== null) {
                    $this->files->put($path, (string) $fileContents);
                }

                $this->checks->save($done);
                $this->audit->record(new AuditEntry(
                    AuditActorType::User, $team->member->userId, AuditAction::CHECK_OUTCOME_RECORDED, 'application_check', $checkId,
                    ['application_id' => $applicationId, 'kind' => $type->kind->value, 'outcome' => $outcome->value, 'has_file' => $path !== null], $ipAddress, $userAgent, $now, $team->member->companyId,
                ));

                return $done;
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                $this->deleteQuietly($path);
            }

            throw $e;
        }
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('The file of a check result could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
