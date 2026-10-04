<?php

namespace Src\Identity\Application\Terms;

use Src\Identity\Domain\TermsAcceptanceRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Legal\ActiveLegalVersions;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Records that the user accepted the legal documents in force. The user sends the version of every document in force that they
 * saw; if a newer version was published meanwhile the acceptance is refused, so nobody accepts a text they did not read.
 */
final readonly class AcceptTerms
{
    public function __construct(
        private ActiveLegalVersions $activeVersions,
        private TermsAcceptanceRepository $acceptances,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, int>  $versions  document type mapped to the version the user saw
     *
     * @throws BusinessRuleViolation `legal-document-missing` when a document in force is left out, `legal-document-not-active` for a type that is not in force
     * @throws StateConflict `legal-document-outdated` when a version is no longer the one in force
     */
    public function execute(Actor $actor, array $versions, string $locale, ?string $ipAddress): void
    {
        $active = $this->activeVersions->active();

        $notActive = array_values(array_diff(array_keys($versions), array_keys($active)));
        if ($notActive !== []) {
            throw new BusinessRuleViolation('A document that is not in force was sent.', 'legal-document-not-active', extensions: ['types' => $notActive]);
        }

        $missing = array_values(array_diff(array_keys($active), array_keys($versions)));
        if ($missing !== []) {
            throw new BusinessRuleViolation('Every document in force must be accepted together.', 'legal-document-missing', extensions: ['types' => $missing]);
        }

        $outdated = [];
        foreach ($active as $type => $version) {
            if ($versions[$type] !== $version) {
                $outdated[] = ['type' => $type, 'version' => $version];
            }
        }

        if ($outdated !== []) {
            throw new StateConflict('A newer version was published. Read it and accept again.', 'legal-document-outdated', extensions: ['required_documents' => $outdated]);
        }

        $now = $this->clock->now();

        $this->transactions->run(function () use ($actor, $active, $locale, $ipAddress, $now): void {
            foreach ($active as $type => $version) {
                $this->acceptances->record((int) $actor->id, $type, $version, $locale, $ipAddress, $now);
            }
        });
    }
}
