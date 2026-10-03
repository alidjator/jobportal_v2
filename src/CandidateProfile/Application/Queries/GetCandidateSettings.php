<?php

namespace Src\CandidateProfile\Application\Queries;

use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\CandidateSettingsRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** GET /candidate/settings?type=personal|profile|social|contact. */
class GetCandidateSettings
{
    public function __construct(
        private CandidateRepository $candidates,
        private CandidateSettingsRepository $settings,
    ) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, string $type): array
    {
        $candidateId = $this->candidates->ensureIdForUser($userId);

        return match ($type) {
            'personal' => $this->settings->personal($userId, $candidateId),
            'profile' => $this->settings->profile($candidateId),
            'social' => $this->settings->social($userId),
            'contact' => $this->settings->contact($userId, $candidateId),
            default => throw new BusinessRuleViolation('Invalid settings type'),
        };
    }
}
