<?php

namespace Src\Assessment\Application\Delivery;

use Psr\Log\LoggerInterface;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationAttachment;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * The team puts one file on a test invitation (a PDF, JPEG or PNG: a map, a preparation sheet), replaces it, or takes it off, while the candidate has not started. The
 * new file is stored first and the invitation changed next; the old file is deleted last, and a failure to delete it only leaves a stray file behind.
 */
final readonly class ChangeInvitationAttachment
{
    private const string DIRECTORY = 'assessment/invitation-files';

    private const int MAX_NAME_LENGTH = 255;

    public function __construct(
        private TeamApplications $applications,
        private InvitationRepository $invitations,
        private InvitationViews $views,
        private AttachmentContent $content,
        private Settings $settings,
        private FileStorage $files,
        private TransactionManager $transactions,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `file-too-large` or `invalid-attachment`
     * @throws StateConflict `invitation-not-active`
     */
    public function put(Actor $actor, int $invitationId, string $contents, string $originalName): InvitationView
    {
        $invitation = $this->visible($actor, $invitationId);
        $maxMb = $this->settings->integer(AssessmentSettings::MAX_ATTACHMENT_MEGABYTES, $invitation->companyId);

        if (strlen($contents) > $maxMb * 1024 * 1024) {
            throw new BusinessRuleViolation("The file is larger than the allowed {$maxMb} MB.", 'file-too-large', extensions: ['max_size_mb' => $maxMb]);
        }

        $file = $this->content->process($contents);
        $path = self::DIRECTORY.'/'.$invitationId.'/'.bin2hex(random_bytes(16)).'.'.$file['extension'];
        $attachment = new InvitationAttachment($path, $this->cleanName($originalName, $file['extension']), $file['mime_type'], strlen($file['contents']));

        $this->files->put($path, $file['contents']);

        try {
            [$saved, $previous] = $this->swap($actor, $invitation, $attachment);
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        if ($previous !== null) {
            $this->deleteQuietly($previous);
        }

        return $this->views->one($saved);
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `invitation-not-active`
     */
    public function remove(Actor $actor, int $invitationId): InvitationView
    {
        [$saved, $previous] = $this->swap($actor, $this->visible($actor, $invitationId), null);

        if ($previous !== null) {
            $this->deleteQuietly($previous);
        }

        return $this->views->one($saved);
    }

    private function visible(Actor $actor, int $invitationId): Invitation
    {
        $invitation = $this->invitations->find($invitationId) ?? throw EntityNotFound::of('Invitation', $invitationId);
        $this->applications->find($actor, $invitation->applicationId, permission: CompanyPermission::AssessmentsSend);

        return $invitation;
    }

    /** @return array{0: Invitation, 1: string|null} the changed invitation and the path of the file it replaced */
    private function swap(Actor $actor, Invitation $invitation, ?InvitationAttachment $attachment): array
    {
        return $this->transactions->run(function () use ($actor, $invitation, $attachment): array {
            // The application first, as every other change of an application does.
            $this->applications->find($actor, $invitation->applicationId, forUpdate: true, permission: CompanyPermission::AssessmentsSend);
            $current = $this->invitations->find((int) $invitation->id, forUpdate: true) ?? throw EntityNotFound::of('Invitation', (int) $invitation->id);

            $changed = $current->withAttachment($attachment);
            $this->invitations->update($changed);

            return [$changed, $current->attachment?->path];
        });
    }

    /** The client's name is kept for display only: no path, no control characters, at most 255 characters, and an extension that matches the content. */
    private function cleanName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', mb_scrub($name)));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $stem = $stem === '' ? 'attachment' : $stem;

        return mb_substr($stem, 0, self::MAX_NAME_LENGTH - strlen($extension) - 1).'.'.$extension;
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('An invitation file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
