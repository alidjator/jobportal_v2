<?php

namespace Src\Assessment\Application\Institution;

use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The psychologist reads their own institution: its name and the identity printed on reports. Any psychologist of it may read; only a lead changes it. */
final readonly class GetInstitution
{
    public function __construct(
        private ReviewAccess $access,
        private ProviderRepository $providers,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor): Provider
    {
        $psychologist = $this->access->psychologist($actor);

        return $this->providers->findById($psychologist->providerId) ?? throw EntityNotFound::of('Provider', $psychologist->providerId);
    }
}
