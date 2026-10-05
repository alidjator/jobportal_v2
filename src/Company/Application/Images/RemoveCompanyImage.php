<?php

namespace Src\Company\Application\Images;

use Psr\Log\LoggerInterface;
use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Throwable;

/** A company admin removes the logo or the banner. Idempotent: removing a picture that is not there changes nothing. */
final readonly class RemoveCompanyImage
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /** @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended` */
    public function execute(Actor $actor, CompanyImageKind $kind): void
    {
        $company = $this->guard->company($actor);
        $path = $kind === CompanyImageKind::Logo ? $company->logoPath() : $company->bannerPath();

        if ($path === null) {
            return;
        }

        $kind === CompanyImageKind::Logo ? $company->changeLogo(null) : $company->changeBanner(null);
        $this->companies->save($company);

        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A company image file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
