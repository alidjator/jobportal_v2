<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Domain\CvTemplateState;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** An admin switches a CV template on or off and/or makes it the default. */
final readonly class UpdateCvTemplate
{
    public function __construct(private CvTemplates $templates) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `cv-template-required`
     */
    public function execute(Actor $actor, string $key, ?bool $enabled, ?bool $isDefault): CvTemplateState
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator manages CV templates.', 'admin-only');
        }

        return $this->templates->update($key, $enabled, $isDefault);
    }
}
