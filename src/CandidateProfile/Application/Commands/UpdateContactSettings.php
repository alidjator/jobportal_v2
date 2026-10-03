<?php

namespace Src\CandidateProfile\Application\Commands;

use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\CandidateSettingsRepository;

/** POST /candidate/settings type=contact. */
class UpdateContactSettings
{
    public function __construct(
        private CandidateRepository $candidates,
        private CandidateSettingsRepository $settings,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function __invoke(int $userId, array $input): array
    {
        $keys = ['phone', 'secondary_phone', 'email', 'secondary_email', 'whatsapp_number', 'country', 'address', 'exact_location', 'lat', 'long'];
        $d = [];
        foreach ($keys as $key) {
            $d[$key] = $input[$key] ?? null;
        }

        $this->settings->updateContact($userId, $this->candidates->ensureIdForUser($userId), $d);

        return [
            'message' => 'Contact Information Updated Successful!',
            'data' => [
                'contact_info' => [
                    'phone' => $d['phone'],
                    'secondary_phone' => $d['secondary_phone'],
                    'whatsapp_no' => $d['whatsapp_number'],
                    'secondary_email' => $d['secondary_email'],
                ],
                'location' => [
                    'country' => $d['country'],
                    'address' => $d['address'],
                    'exact_location' => $d['exact_location'],
                    'latitude' => $d['lat'],
                    'longitude' => $d['long'],
                ],
            ],
        ];
    }
}
