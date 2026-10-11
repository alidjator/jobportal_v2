<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class ListPsychologists
{
    public function __construct(
        private ProviderRepository $providers,
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private Clock $clock,
    ) {}

    /**
     * @return Page<PsychologistListItem>
     *
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the provider does not exist
     */
    public function execute(Actor $actor, int $providerId, PageRequest $request): Page
    {
        AdminOnly::assert($actor, 'list psychologists');

        $this->providers->findById($providerId) ?? throw EntityNotFound::of('Provider', $providerId);

        $page = $this->psychologists->pageOfProvider($providerId, $request);
        $summaries = $this->accounts->summaries(array_map(fn ($psychologist): int => $psychologist->userId, $page->items));

        $now = $this->clock->now();

        return new Page(
            array_map(fn ($psychologist): PsychologistListItem => new PsychologistListItem(
                $psychologist, $summaries[$psychologist->userId] ?? null, $psychologist->canSignReports($now),
            ), $page->items),
            $page->total,
            $page->request,
        );
    }
}
