<?php

namespace Src\Identity\Application\Terms;

use Src\Shared\Domain\Exceptions\StateConflict;

/** The terms gate: refuses a user who has not accepted every legal document in force. */
final readonly class EnsureTermsAccepted
{
    public function __construct(private TermsAcceptanceChecker $checker) {}

    /** @throws StateConflict `terms-not-accepted`, carrying the documents still to accept */
    public function execute(int $userId): void
    {
        $missing = [];

        foreach ($this->checker->requirements($userId) as $requirement) {
            if (! $requirement->accepted) {
                $missing[] = ['type' => $requirement->type, 'version' => $requirement->version];
            }
        }

        if ($missing !== []) {
            throw new StateConflict(
                'You must accept the current terms and privacy policy before continuing.',
                'terms-not-accepted',
                extensions: ['required_documents' => $missing],
            );
        }
    }
}
