<?php

namespace Src\Recruitment\Application;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The resume copy of an application for a member who may see its job. An application whose copy was erased (the account was anonymised) or whose file is
 * missing answers as not found; a missing file is also reported. Every download is audited (`application.resume_downloaded`) for the company activity log.
 */
final readonly class GetCompanyApplicationResume
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ContactVisibility $contact,
        private ApplicationRepository $applications,
        private FileStorage $files,
        private AuditTrail $audit,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `contact-not-available`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress = null, ?string $userAgent = null): ApplicationFile
    {
        $member = $this->access->viewer($actor);
        $application = $this->applications->find($id) ?? throw EntityNotFound::of('Application', $id);
        $this->applicationAccess->assertCanSee($member, $id, $application->companyId, $application->jobId);

        $resume = $application->resume ?? throw EntityNotFound::of('Resume', $id);

        if ($this->contact->isClosed($application->companyId, $application->stageType)) {
            throw new AccessDenied('The resume opens when the application moves to screening, or once the company is verified.', 'contact-not-available');
        }

        $contents = $this->files->get($resume->path);

        if ($contents === null) {
            $this->logger->error('The resume copy of an application is missing.', ['application_id' => $id, 'path' => $resume->path]);

            throw EntityNotFound::of('Resume', $id);
        }

        $this->audit->record(new AuditEntry(
            AuditActorType::User, $actor->id, AuditAction::APPLICATION_RESUME_DOWNLOADED, 'application', $id, [], $ipAddress, $userAgent, $this->clock->now(), $application->companyId,
        ));

        return new ApplicationFile($contents, $resume->name);
    }
}
