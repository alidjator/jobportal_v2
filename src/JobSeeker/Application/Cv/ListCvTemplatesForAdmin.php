<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Domain\CvTemplateState;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** Every CV template with its state, for an admin who holds `cv_templates.manage` (the route checks the permission; this checks the kind of account). */
final readonly class ListCvTemplatesForAdmin
{
    public function __construct(private CvTemplates $templates) {}

    /**
     * @return list<CvTemplateState>
     *
     * @throws AccessDenied
     */
    public function execute(Actor $actor): array
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator manages CV templates.', 'admin-only');
        }

        return $this->templates->all();
    }
}
