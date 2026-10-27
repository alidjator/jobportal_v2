<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Application\Register\RegisterItem;
use Src\Assessment\Application\Register\RegisterItems;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\RegisterFilter;
use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The report register of one provider, read by the platform admin who manages providers: the same lines the psychologists of the provider see. */
final readonly class ListProviderRegister
{
    public function __construct(
        private ProviderRepository $providers,
        private ReportRepository $reports,
        private RegisterItems $items,
    ) {}

    /**
     * @return Page<RegisterItem>
     *
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the provider does not exist
     */
    public function execute(Actor $actor, int $providerId, RegisterFilter $filter, PageRequest $request): Page
    {
        AdminOnly::assert($actor, 'read the report register');

        $this->providers->findById($providerId) ?? throw EntityNotFound::of('Provider', $providerId);

        return $this->items->of($this->reports->registerOfProvider($providerId, $filter, $request));
    }
}
