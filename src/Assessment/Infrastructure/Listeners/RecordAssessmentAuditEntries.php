<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use InvalidArgumentException;
use Src\Assessment\Domain\Events\ProviderAutoIssueChanged;
use Src\Assessment\Domain\Events\ProviderRegistered;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Assessment\Domain\Events\PsychologistInvited;
use Src\Assessment\Domain\Events\PsychologistJoined;
use Src\Assessment\Domain\Events\ReportRegisterExported;
use Src\Assessment\Domain\Events\ReportRevised;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Assessment context: providers registered, psychologists invited, joined and changed by an admin (they
 * decide who may sign psychological reports), reports signed (by a psychologist, or issued automatically, which the system records), revised reports, the download of the report register and the platform admin's gate for automatic issue. Runs on the `default` queue, retried 3 times with a growing delay. The entries never hold an email address.
 */
final class RecordAssessmentAuditEntries implements ShouldQueue
{
    private const string PROVIDER_SUBJECT = 'assessment_provider';

    private const string PSYCHOLOGIST_SUBJECT = 'assessment_psychologist';

    private const string REPORT_SUBJECT = 'assessment_report';

    private const array CHANGE_ACTIONS = [
        'credentials_changed' => AuditAction::ASSESSMENT_PSYCHOLOGIST_CREDENTIALS_CHANGED,
        'role_changed' => AuditAction::ASSESSMENT_PSYCHOLOGIST_ROLE_CHANGED,
        'suspended' => AuditAction::ASSESSMENT_PSYCHOLOGIST_SUSPENDED,
        'restored' => AuditAction::ASSESSMENT_PSYCHOLOGIST_RESTORED,
        'two_factor_reset' => AuditAction::ASSESSMENT_PSYCHOLOGIST_TWO_FACTOR_RESET,
    ];

    public int $tries = 3;

    public function __construct(private readonly AuditTrail $trail) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onProviderRegistered(ProviderRegistered $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::ASSESSMENT_PROVIDER_REGISTERED,
            self::PROVIDER_SUBJECT, $event->providerId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onPsychologistInvited(PsychologistInvited $event): void
    {
        $this->trail->record(new AuditEntry(
            $event->adminId === null ? AuditActorType::User : AuditActorType::Admin, $event->adminId ?? $event->userId, AuditAction::ASSESSMENT_PSYCHOLOGIST_INVITED,
            self::PROVIDER_SUBJECT, $event->providerId, ['invitation_id' => $event->invitationId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onReportSigned(ReportSigned $event): void
    {
        if ($event->autoIssued) {
            $this->trail->record(new AuditEntry(
                AuditActorType::System, null, AuditAction::ASSESSMENT_REPORT_AUTO_ISSUED,
                self::REPORT_SUBJECT, $event->reportId,
                ['report_number' => $event->reportNumber, 'invitation_id' => $event->invitationId, 'provider_id' => $event->providerId, 'responsible_user_id' => $event->psychologistUserId],
                null, null, $event->occurredAt(),
            ));

            return;
        }

        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->psychologistUserId, AuditAction::ASSESSMENT_REPORT_SIGNED,
            self::REPORT_SUBJECT, $event->reportId, ['report_number' => $event->reportNumber, 'invitation_id' => $event->invitationId, 'provider_id' => $event->providerId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onReportRevised(ReportRevised $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->psychologistUserId, AuditAction::ASSESSMENT_REPORT_REVISED,
            self::REPORT_SUBJECT, $event->reportId,
            ['report_number' => $event->reportNumber, 'revision' => $event->revision, 'invitation_id' => $event->invitationId, 'provider_id' => $event->providerId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onReportRegisterExported(ReportRegisterExported $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->psychologistUserId, AuditAction::ASSESSMENT_REGISTER_EXPORTED,
            self::PROVIDER_SUBJECT, $event->providerId, ['filters' => $event->filters, 'row_count' => $event->rowCount],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onProviderAutoIssueChanged(ProviderAutoIssueChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::ASSESSMENT_PROVIDER_AUTO_ISSUE_CHANGED,
            self::PROVIDER_SUBJECT, $event->providerId, ['allowed' => $event->allowed], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onPsychologistJoined(PsychologistJoined $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::ASSESSMENT_PSYCHOLOGIST_JOINED,
            self::PROVIDER_SUBJECT, $event->providerId, ['psychologist_id' => $event->psychologistId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onPsychologistChanged(PsychologistChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            $event->adminId === null ? AuditActorType::User : AuditActorType::Admin, $event->adminId ?? $event->userId,
            self::CHANGE_ACTIONS[$event->change] ?? throw new InvalidArgumentException("Unknown psychologist change {$event->change}."),
            self::PSYCHOLOGIST_SUBJECT, $event->psychologistId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
