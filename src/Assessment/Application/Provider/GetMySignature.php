<?php

namespace Src\Assessment\Application\Provider;

use Src\Assessment\Application\Delivery\AttachmentFile;
use Src\Assessment\Application\Institution\PrivateImages;
use Src\Assessment\Application\Review\ReviewAccess;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The psychologist's own signature image, for the screen that edits it. Nobody else's. */
final readonly class GetMySignature
{
    public function __construct(
        private ReviewAccess $access,
        private PrivateImages $images,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound when the psychologist has no signature image
     */
    public function execute(Actor $actor): AttachmentFile
    {
        $path = $this->access->psychologist($actor)->signaturePath() ?? throw new EntityNotFound('You have no signature image.', 'image-not-found');

        return $this->images->read($path, 'signature');
    }
}
