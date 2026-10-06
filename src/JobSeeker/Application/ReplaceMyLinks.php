<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\LinkRules;
use Src\JobSeeker\Domain\LinkType;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A job seeker sets their whole list of links; every address and the number of links per kind are checked by `LinkRules`. */
final readonly class ReplaceMyLinks
{
    public function __construct(private JobSeekerGuard $guard, private ProfileListRepository $lists, private TransactionManager $transactions) {}

    /**
     * @param  list<array{type: LinkType, url: string}>  $links
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `invalid-links` with `errors` per `links.<n>.<field>`
     */
    public function execute(Actor $actor, array $links): void
    {
        $userId = $this->guard->userId($actor);
        $checked = LinkRules::checkAll($links);

        $this->transactions->run(fn () => $this->lists->replaceLinks($userId, $checked));
    }
}
