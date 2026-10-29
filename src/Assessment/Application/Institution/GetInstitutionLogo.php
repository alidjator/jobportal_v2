<?php

namespace Src\Assessment\Application\Institution;

use Src\Assessment\Application\Delivery\AttachmentFile;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The logo of the psychologist's own institution, for the screen that edits it. */
final readonly class GetInstitutionLogo
{
    public function __construct(
        private GetInstitution $institution,
        private PrivateImages $images,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound when the institution has no logo
     */
    public function execute(Actor $actor): AttachmentFile
    {
        $path = $this->institution->execute($actor)->identity()->logoPath ?? throw new EntityNotFound('The institution has no logo.', 'image-not-found');

        return $this->images->read($path, 'logo');
    }
}
