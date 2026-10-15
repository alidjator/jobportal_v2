<?php

namespace Src\Company\Application\NameChange;

use Psr\Log\LoggerInterface;
use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyNameChangeRequested;
use Src\Company\Domain\NameChangeRequest;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A member who may change the company profile asks to rename a verified company, with a reason and a PDF that proves it. An unverified company renames itself
 * in the profile, so it is told to (409 `name-not-locked`). One request waits at a time per company. As with documents, the file is stored first and the
 * database changed next; a file left behind by a failure is deleted.
 */
final readonly class RequestNameChange
{
    private const string DIRECTORY = 'company/name-changes';

    private const int NAME_MIN = 2;

    private const int NAME_MAX = 255;

    private const int REASON_MIN = 5;

    private const int REASON_MAX = 1000;

    private const int FILE_NAME_MAX = 255;

    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private NameChangeRequestRepository $requests,
        private NameChangeDocumentChecker $checker,
        private FileStorage $files,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws StateConflict `name-not-locked` or `name-change-pending`
     * @throws BusinessRuleViolation `invalid-name-change` with `errors` per field, `file-too-large` or `invalid-document`
     */
    public function execute(Actor $actor, string $newName, string $reason, string $contents, string $originalName, ?string $ipAddress, ?string $userAgent): NameChangeRequest
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);
        $companyId = (int) $company->id;

        if ($company->verifiedAt() === null) {
            throw new StateConflict('The name of this company is not locked: change it in the company profile.', 'name-not-locked');
        }

        $newName = trim((string) preg_replace('/\s+/u', ' ', $newName));
        $reason = trim($reason);
        $this->assertFields($company->name(), $newName, $reason);
        $this->checker->check($contents);

        $path = self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.pdf';
        $now = $this->clock->now();

        $this->files->put($path, $contents);

        try {
            $request = $this->transactions->run(function () use ($companyId, $actor, $company, $newName, $reason, $path, $originalName): NameChangeRequest {
                // The company row is locked, so two requests of one company run one after the other.
                $this->companies->lock($companyId);

                if ($this->requests->pendingOf($companyId) !== null) {
                    throw new StateConflict('A name change request of this company is already waiting for a decision.', 'name-change-pending');
                }

                return $this->requests->create(NameChangeRequest::submit($companyId, (int) $actor->id, $company->name(), $newName, $reason, $path, $this->cleanName($originalName)));
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        $this->events->dispatch([new CompanyNameChangeRequested($companyId, (int) $request->id, (int) $actor->id, $ipAddress, $userAgent, $now)]);

        return $request;
    }

    /** @throws BusinessRuleViolation `invalid-name-change` */
    private function assertFields(string $currentName, string $newName, string $reason): void
    {
        $errors = [];
        $length = mb_strlen($newName);

        if ($length < self::NAME_MIN || $length > self::NAME_MAX) {
            $errors['new_name'] = ['The new name must have between '.self::NAME_MIN.' and '.self::NAME_MAX.' characters.'];
        } elseif (mb_strtolower($newName) === mb_strtolower($currentName)) {
            $errors['new_name'] = ['The new name is the same as the current name.'];
        }

        $reasonLength = mb_strlen($reason);

        if ($reasonLength < self::REASON_MIN || $reasonLength > self::REASON_MAX) {
            $errors['reason'] = ['The reason must have between '.self::REASON_MIN.' and '.self::REASON_MAX.' characters.'];
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The name change request is not valid.', 'invalid-name-change', extensions: ['errors' => $errors]);
        }
    }

    /** The client's name is kept for display only: no path, no control characters, at most 255 characters. */
    private function cleanName(string $name): string
    {
        $name = mb_scrub($name);
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));

        return $name === '' ? 'document.pdf' : mb_substr($name, 0, self::FILE_NAME_MAX);
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A name change document could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
