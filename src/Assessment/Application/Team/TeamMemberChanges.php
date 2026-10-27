<?php

namespace Src\Assessment\Application\Team;

use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\PsychologistRole;
use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The changes to a psychologist's standing that both a platform admin and a lead of the institution make: suspend, restore and change the role. The
 * provider row is locked first, so two leads cannot suspend each other at the same moment and leave the institution without a lead. `$providerId` is
 * the lead's institution (a psychologist of another institution is a 404); an admin passes null.
 */
final readonly class TeamMemberChanges
{
    public function __construct(
        private ProviderRepository $providers,
        private PsychologistRepository $psychologists,
        private ReportRepository $reports,
        private PsychologistAccounts $accounts,
        private TransactionManager $transactions,
    ) {}

    /**
     * Closes the account, signs the psychologist out everywhere and releases the reports they held.
     *
     * @throws EntityNotFound
     * @throws StateConflict `cannot-suspend-self` (`$ownUserId` is the account of the lead acting) or `last-lead`
     */
    public function suspend(int $psychologistId, ?int $providerId, ?int $ownUserId = null): void
    {
        $this->transactions->run(function () use ($psychologistId, $providerId, $ownUserId): void {
            $psychologist = $this->lock($psychologistId, $providerId);

            if ($ownUserId !== null && $psychologist->userId === $ownUserId) {
                throw new StateConflict('You cannot suspend yourself.', 'cannot-suspend-self');
            }

            $this->assertAnotherLeadStays($psychologist);

            $psychologist->suspend();
            $this->psychologists->save($psychologist);
            $this->reports->releaseClaimsOf($psychologistId);
            $this->accounts->deactivate($psychologist->userId);
        });
    }

    /** @throws EntityNotFound */
    public function restore(int $psychologistId, ?int $providerId): void
    {
        $this->transactions->run(function () use ($psychologistId, $providerId): void {
            $psychologist = $this->lock($psychologistId, $providerId);

            $psychologist->restore();
            $this->psychologists->save($psychologist);
            $this->accounts->reactivate($psychologist->userId);
        });
    }

    /**
     * Returns the psychologist as saved, and whether the role changed.
     *
     * @return array{Psychologist, bool}
     *
     * @throws EntityNotFound
     * @throws StateConflict `last-lead` when the last active lead would become a member
     */
    public function changeRole(int $psychologistId, PsychologistRole $role, ?int $providerId): array
    {
        return $this->transactions->run(function () use ($psychologistId, $role, $providerId): array {
            $psychologist = $this->lock($psychologistId, $providerId);

            if ($psychologist->role() === $role) {
                return [$psychologist, false];
            }

            if (! $role->isLead()) {
                $this->assertAnotherLeadStays($psychologist);
            }

            $psychologist->changeRole($role);

            return [$this->psychologists->save($psychologist), true];
        });
    }

    /** @throws EntityNotFound */
    private function lock(int $psychologistId, ?int $providerId): Psychologist
    {
        $psychologist = $this->psychologists->findById($psychologistId) ?? throw EntityNotFound::of('Psychologist', $psychologistId);

        if ($providerId !== null && $psychologist->providerId !== $providerId) {
            throw EntityNotFound::of('Psychologist', $psychologistId);
        }

        $this->providers->findById($psychologist->providerId, forUpdate: true);

        return $this->psychologists->findById($psychologistId, forUpdate: true) ?? throw EntityNotFound::of('Psychologist', $psychologistId);
    }

    /** @throws StateConflict `last-lead` when the psychologist is the only active lead */
    private function assertAnotherLeadStays(Psychologist $psychologist): void
    {
        if ($psychologist->isLead() && $psychologist->isActive() && $this->psychologists->activeLeadCount($psychologist->providerId) <= 1) {
            throw new StateConflict('An institution keeps at least one active lead.', 'last-lead');
        }
    }
}
