<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\ProviderStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

final readonly class ListProviders
{
    public function __construct(private ProviderRepository $providers) {}

    /**
     * @return Page<Provider>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, ?string $search, ?ProviderStatus $status, PageRequest $request): Page
    {
        AdminOnly::assert($actor, 'list providers');

        return $this->providers->page($search, $status, $request);
    }
}
