<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\Report;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The claim on a report (`docs/design/phase-8-provider-tools.md` section 3): a psychologist claims it before editing, scoring drawings, signing, marking it reviewed or
 * revising it, and each write by the holder keeps the claim alive for another 24 hours. Reading is open to every psychologist of the provider.
 */
final readonly class ReviewClaims
{
    public function __construct(
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private Clock $clock,
    ) {}

    /**
     * @throws StateConflict `claim-required`
     */
    public function assertHeld(Psychologist $psychologist, Report $report): void
    {
        if (! $report->isClaimedBy((int) $psychologist->id, $this->clock->now())) {
            throw new StateConflict('Claim this report before you change it.', 'claim-required');
        }
    }

    /**
     * Who holds the report now, or null when nobody does (a claim older than 24 hours without a write counts as none).
     *
     * @return array{id: int, name: string, mine: bool}|null
     */
    public function holder(Psychologist $viewer, Report $report): ?array
    {
        $holders = $this->holders($viewer, [$report->claimedByPsychologistId], $report->hasLiveClaim($this->clock->now()));

        return $report->claimedByPsychologistId === null ? null : ($holders[$report->claimedByPsychologistId] ?? null);
    }

    /**
     * The holders of these claims by psychologist id, with their names. `$live` says the claim of a single report is live; a list passes the live ids only.
     *
     * @param  list<int|null>  $psychologistIds
     * @return array<int, array{id: int, name: string, mine: bool}>
     */
    public function holders(Psychologist $viewer, array $psychologistIds, bool $live = true): array
    {
        $ids = array_values(array_unique(array_filter($psychologistIds, static fn (?int $id): bool => $id !== null)));

        if (! $live || $ids === []) {
            return [];
        }

        $userIds = $this->psychologists->userIdsOf($ids);
        $names = $this->accounts->summaries(array_values($userIds));
        $holders = [];

        foreach ($ids as $id) {
            $holders[$id] = ['id' => $id, 'name' => isset($userIds[$id], $names[$userIds[$id]]) ? $names[$userIds[$id]]->name : '', 'mine' => $id === $viewer->id];
        }

        return $holders;
    }
}
