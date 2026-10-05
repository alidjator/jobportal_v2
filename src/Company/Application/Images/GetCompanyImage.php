<?php

namespace Src\Company\Application\Images;

use Src\Company\Application\Profile\CompanyMemberGuard;
use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Hands out a company picture. The public side shows it only for a company the public may see; the member's own side shows it whatever the
 * company's visibility or status. A company without that picture, or a file that has gone missing, is the same 404.
 */
final readonly class GetCompanyImage
{
    public function __construct(
        private CompanyRepository $companies,
        private CompanyMemberGuard $memberGuard,
        private FileStorage $files,
    ) {}

    /** @throws EntityNotFound `image-not-found` */
    public function ofPublicCompany(string $slug, CompanyImageKind $kind): CompanyImageContent
    {
        $company = $this->companies->findBySlug($slug);

        if ($company === null || ! $company->isPublic()) {
            throw $this->notFound();
        }

        return $this->read($company, $kind);
    }

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound `image-not-found`
     */
    public function ofOwnCompany(Actor $actor, CompanyImageKind $kind): CompanyImageContent
    {
        return $this->read($this->memberGuard->company($actor), $kind);
    }

    private function read(Company $company, CompanyImageKind $kind): CompanyImageContent
    {
        $path = $kind === CompanyImageKind::Logo ? $company->logoPath() : $company->bannerPath();
        $contents = $path === null ? null : $this->files->get($path);

        if ($path === null || $contents === null) {
            throw $this->notFound();
        }

        return new CompanyImageContent($contents, CompanyImageFile::mimeType($path), CompanyImageFile::version($path));
    }

    private function notFound(): EntityNotFound
    {
        return new EntityNotFound('Image not found.', 'image-not-found');
    }
}
