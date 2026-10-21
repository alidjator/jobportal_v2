<?php

namespace Src\Company\Application\Documents;

use Psr\Log\LoggerInterface;
use Src\Company\Application\Images\CompanySettings;
use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Company\Domain\CompanyDocument;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\CompanyDocumentSubmitted;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Throwable;

/**
 * A company admin submits the document for one type, or replaces the one already there. A replacement puts the document back to `pending`
 * (clearing any rejection) and, when the old document was approved, withdraws the badge. As with images, the new file is stored first and the
 * database changed next; the old file is deleted last, and a failure to delete it only leaves a stray file behind.
 */
final readonly class SubmitCompanyDocument
{
    private const string DIRECTORY = 'company/documents';

    private const int MAX_NAME_LENGTH = 255;

    public function __construct(
        private CompanyAdminGuard $guard,
        private DocumentTypeRepository $types,
        private CompanyDocumentRepository $documents,
        private CompanyRepository $companies,
        private CompanyVerificationBadge $badge,
        private DocumentFileProcessor $processor,
        private Settings $settings,
        private FileStorage $files,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return bool true when the document was created, false when it replaced an earlier one
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the type does not exist
     * @throws BusinessRuleViolation `document-type-inactive`, `file-too-large` or `invalid-document`
     */
    public function execute(Actor $actor, int $typeId, string $contents, string $originalName, ?string $ipAddress, ?string $userAgent): bool
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;

        $type = $this->types->find($typeId) ?? throw EntityNotFound::of('Document type', $typeId);

        if (! $type->isActive) {
            throw new BusinessRuleViolation('This document type is not accepted at the moment.', 'document-type-inactive');
        }

        $maxKb = $this->settings->integer(CompanySettings::DOCUMENT_MAX_SIZE_KB, $companyId);

        if (strlen($contents) > $maxKb * 1024) {
            throw new BusinessRuleViolation('The file is larger than the allowed :max KB.', 'file-too-large', extensions: ['max_size_kb' => $maxKb], parameters: ['max' => $maxKb]);
        }

        $file = $this->processor->process($contents);
        $path = self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.'.$file->extension;
        $name = $this->cleanName($originalName);
        $now = $this->clock->now();

        $this->files->put($path, $file->contents);

        try {
            /** @var CompanyDocument|null $previous */
            $previous = $this->transactions->run(function () use ($companyId, $typeId, $path, $name, $file, $now): ?CompanyDocument {
                // The company row is locked, so two uploads for the same company run one after the other.
                $this->companies->lock($companyId);
                $existing = $this->documents->find($companyId, $typeId);

                if ($existing === null) {
                    $this->documents->create(CompanyDocument::submit($companyId, $typeId, $path, $name, $file->mimeType, strlen($file->contents), $now));

                    return null;
                }

                $this->documents->save($existing->resubmit($path, $name, $file->mimeType, strlen($file->contents), $now));

                if ($existing->isApproved()) {
                    $this->badge->recalculateFor($companyId);
                }

                return $existing;
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        if ($previous !== null) {
            $this->deleteQuietly($previous->filePath);
        }

        $this->events->dispatch([new CompanyDocumentSubmitted($companyId, $typeId, $previous !== null, (int) $actor->id, $ipAddress, $userAgent, $now)]);

        return $previous === null;
    }

    /** The client's name is kept for display only: no path, no control characters, at most 255 characters. */
    private function cleanName(string $name): string
    {
        $name = mb_scrub($name);
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));

        return $name === '' ? 'document' : mb_substr($name, 0, self::MAX_NAME_LENGTH);
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A company document file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
