<?php

namespace Src\Assessment\Application\Team;

use Src\Assessment\Application\Admin\PsychologistListItem;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The psychologists of the lead's institution, with their account and whether each may sign today. */
final readonly class ListTeam
{
    public function __construct(
        private TeamAccess $access,
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private Clock $clock,
    ) {}

    /**
     * @return list<PsychologistListItem>
     *
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     */
    public function execute(Actor $actor): array
    {
        $lead = $this->access->lead($actor);
        $team = $this->psychologists->ofProvider($lead->providerId);
        $summaries = $this->accounts->summaries(array_map(fn ($psychologist): int => $psychologist->userId, $team));
        $now = $this->clock->now();

        return array_map(fn ($psychologist): PsychologistListItem => new PsychologistListItem(
            $psychologist, $summaries[$psychologist->userId] ?? null, $psychologist->canSignReports($now),
        ), $team);
    }
}
