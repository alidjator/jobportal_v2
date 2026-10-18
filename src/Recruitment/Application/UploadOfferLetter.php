<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\OfferLetterUpload;
use Src\Recruitment\Domain\OfferLetterUploadRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The team uploads the company's own letter for an application before sending the offer that uses it. The file must be a PDF (checked by its content, never by
 * its name or the type the client sent) of at most 10 MB. The upload waits {@see OfferLetterUpload::VALID_HOURS} hours for the offer; an unused one is deleted
 * by the daily purge.
 */
final readonly class UploadOfferLetter
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    private const string DIRECTORY = 'recruitment/offer-letter-uploads';

    private const string PDF_SIGNATURE = '%PDF-';

    public function __construct(
        private TeamApplicationLookup $lookup,
        private OfferLetterUploadRepository $uploads,
        private FileStorage $files,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `file-too-large` or `invalid-letter`
     */
    public function execute(Actor $actor, int $applicationId, string $contents): OfferLetterUpload
    {
        $this->lookup->find($actor, $applicationId, permission: CompanyPermission::OffersManage);

        if (strlen($contents) > self::MAX_BYTES) {
            throw new BusinessRuleViolation('The file is larger than the allowed 10 MB.', 'file-too-large', extensions: ['max_size_mb' => 10]);
        }

        if (! str_starts_with($contents, self::PDF_SIGNATURE)) {
            throw new BusinessRuleViolation('The letter must be a PDF file.', 'invalid-letter', extensions: ['errors' => ['file' => ['The letter must be a PDF file.']]]);
        }

        $path = self::DIRECTORY.'/'.$applicationId.'/'.bin2hex(random_bytes(16)).'.pdf';
        $this->files->put($path, $contents);

        return $this->uploads->add(new OfferLetterUpload($applicationId, $actor->id, $path, strlen($contents), hash('sha256', $contents), $this->clock->now()));
    }
}
