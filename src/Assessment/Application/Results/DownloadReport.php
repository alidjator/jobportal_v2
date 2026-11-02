<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Application\Reports\ReportFile;
use Src\Assessment\Application\Reports\ReportPdfStore;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The PDF of a signed report, for the members who may see the application: the file kept when it was signed (`ReportPdfStore`), so every download is the same bytes. A draft is never handed out. Every download is audited (`assessment.report_downloaded`) for the company activity log. */
final readonly class DownloadReport
{
    public function __construct(
        private VisibleInvitations $visible,
        private AttemptRepository $attempts,
        private ReportRepository $reports,
        private ReportPdfStore $store,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `report-not-signed` or `report-erased`
     */
    public function execute(Actor $actor, int $invitationId, ?string $ipAddress = null, ?string $userAgent = null): ReportFile
    {
        $this->visible->findForResults($actor, $invitationId);
        $attempt = $this->attempts->findByInvitation($invitationId) ?? throw EntityNotFound::of('Report', $invitationId);
        $report = $this->reports->findByAttempt((int) $attempt->id) ?? throw EntityNotFound::of('Report', $invitationId);

        $file = new ReportFile($this->store->fileName($report), $this->store->contents($report));

        $this->audit->record(new AuditEntry(
            AuditActorType::User, $actor->id, AuditAction::ASSESSMENT_REPORT_DOWNLOADED, 'assessment_invitation', $invitationId, [], $ipAddress, $userAgent, $this->clock->now(),
        ));

        return $file;
    }
}
