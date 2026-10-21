<?php

namespace Src\Company\Application\Images;

use Psr\Log\LoggerInterface;
use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Throwable;

/**
 * A company admin uploads or replaces the logo or the banner. The file is checked against the size setting, decoded and encoded again
 * (so nothing but the picture remains) and stored under a random name. The new file is stored first and the database changed next; the
 * old file is deleted last, and a failure to delete it only leaves a stray file behind.
 */
final readonly class UploadCompanyImage
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private Settings $settings,
        private ImageProcessor $images,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws BusinessRuleViolation `file-too-large` or `invalid-image`
     */
    public function execute(Actor $actor, CompanyImageKind $kind, string $contents): void
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);

        $maxBytes = $this->settings->integer($kind->maxSizeSetting()) * 1024;

        if (strlen($contents) > $maxBytes) {
            throw new BusinessRuleViolation(
                'The file is larger than the allowed :max KB.',
                'file-too-large',
                extensions: ['max_size_kb' => intdiv($maxBytes, 1024)],
                parameters: ['max' => intdiv($maxBytes, 1024)],
            );
        }

        $image = $this->images->process($contents, $kind->maxWidth(), $kind->maxHeight());
        $path = $kind->directory().'/'.bin2hex(random_bytes(16)).'.'.$image->extension;
        $previous = $kind === CompanyImageKind::Logo ? $company->logoPath() : $company->bannerPath();

        $this->files->put($path, $image->contents);

        try {
            $kind === CompanyImageKind::Logo ? $company->changeLogo($path) : $company->changeBanner($path);
            $this->companies->save($company);
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        if ($previous !== null) {
            $this->deleteQuietly($previous);
        }
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A company image file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
