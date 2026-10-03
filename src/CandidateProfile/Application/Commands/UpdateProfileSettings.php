<?php

namespace Src\CandidateProfile\Application\Commands;

use Carbon\Carbon;
use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\CandidateSettingsRepository;

/** POST /candidate/settings type=profile. */
class UpdateProfileSettings
{
    public function __construct(
        private CandidateRepository $candidates,
        private CandidateSettingsRepository $settings,
    ) {}

    /**
     * @param  array<string,mixed>  $input  gender,marital_status,profession,status,bio,available_in,skills,languages
     * @return array<string,mixed>
     */
    public function __invoke(int $userId, array $input): array
    {
        $candidateId = $this->candidates->ensureIdForUser($userId);

        $this->settings->updateProfile($candidateId, [
            'gender' => $input['gender'],
            'marital_status' => $input['marital_status'],
            'bio' => $input['bio'],
            'profession' => $input['profession'],
            'status' => $input['status'],
            'available_in' => ! empty($input['available_in']) ? Carbon::parse($input['available_in'])->format('Y-m-d') : null,
        ], array_values($input['skills'] ?? []), array_map('intval', array_values($input['languages'] ?? [])));

        return [
            'message' => 'Profile Info Updated Successful!',
            'data' => $this->settings->profile($candidateId),
        ];
    }
}
