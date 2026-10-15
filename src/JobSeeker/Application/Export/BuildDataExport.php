<?php

namespace Src\JobSeeker\Application\Export;

use DateTimeInterface;
use DateTimeZone;
use Src\JobSeeker\Domain\DataExport;
use Src\JobSeeker\Domain\DataExportRepository;
use Src\JobSeeker\Domain\DataExportStatus;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Notifications\InAppNotifier;
use Throwable;

/**
 * Builds the archive of a requested copy of the personal data, stores it on the private disk for 24 hours, then tells the person (in-app, and by email which is
 * always sent because it answers a request) and records `privacy.data_exported` (who and how large, never the content). Safe to run again: a request that is no
 * longer `building` is left alone, and a file written for a build that then failed is deleted.
 */
final readonly class BuildDataExport
{
    private const string DIRECTORY = 'exports/users';

    public const string NOTIFICATION_TYPE = 'account.data_export_ready';

    public function __construct(
        private DataExportRepository $exports,
        private BuildDataExportArchive $archive,
        private ApplicantContacts $contacts,
        private FileStorage $files,
        private InAppNotifier $notifier,
        private DataExportEmailSender $emails,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    public function execute(int $exportId): void
    {
        $export = $this->exports->find($exportId);

        if ($export === null || $export->status !== DataExportStatus::Building) {
            return;
        }

        $contact = $this->contacts->contact($export->userId);

        // An anonymised account has no owner to give the copy to, and erasure has run or is about to.
        if ($contact === null) {
            $this->exports->markFailed($exportId, 'The account no longer exists.', $this->clock->now());

            return;
        }

        $now = $this->clock->now();
        $bytes = $this->archive->build($export->userId, $now);
        $path = self::DIRECTORY.'/'.$export->userId.'/'.bin2hex(random_bytes(16)).'.zip';
        $expiresAt = $now->modify('+'.DataExport::KEEP_HOURS.' hours');

        $this->files->put($path, $bytes);

        try {
            $this->exports->markReady($exportId, $path, strlen($bytes), $now, $expiresAt);
        } catch (Throwable $e) {
            $this->files->delete($path);

            throw $e;
        }

        $this->audit->record(new AuditEntry(
            AuditActorType::User, $export->userId, AuditAction::PRIVACY_DATA_EXPORTED, 'user', $export->userId, ['export_id' => $exportId, 'size_bytes' => strlen($bytes)], null, null, $now,
        ));
        $this->notifier->send([$export->userId], self::NOTIFICATION_TYPE, ['export_id' => $exportId, 'expires_at' => $expiresAt->format(DateTimeInterface::ATOM)]);

        $this->emails->send($contact, $expiresAt->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i').' WIB');
    }

    /** The build failed for good (every retry is used): the person can ask again at once. */
    public function fail(int $exportId, string $reason): void
    {
        $export = $this->exports->find($exportId);

        if ($export !== null && $export->status === DataExportStatus::Building) {
            $this->exports->markFailed($exportId, $reason, $this->clock->now());
        }
    }
}
