<?php

namespace Src\CandidateProfile\Application\Commands;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\CandidateSettingsRepository;
use Src\Shared\Application\Contracts\FileStorage;

/** POST /candidate/settings type=personal. */
class UpdatePersonalSettings
{
    public function __construct(
        private CandidateRepository $candidates,
        private CandidateSettingsRepository $settings,
        private FileStorage $storage,
    ) {}

    /**
     * @param  array<string,mixed>  $input  name,title,experience_id,education_id,website,date_of_birth
     * @return array<string,mixed>
     */
    public function __invoke(int $userId, array $input, ?UploadedFile $image): array
    {
        $candidateId = $this->candidates->ensureIdForUser($userId);

        $oldPhoto = $this->settings->storedPhoto($candidateId);
        $newPhoto = $image ? $this->storage->storeImage($image, 'images/candidates') : null;

        $this->settings->updatePersonal($userId, $candidateId, [
            'name' => $input['name'],
            'title' => $input['title'],
            'experience_id' => (int) $input['experience_id'],
            'education_id' => (int) $input['education_id'],
            'website' => $input['website'] ?? null,
            'birth_date' => Carbon::parse($input['date_of_birth'])->format('Y-m-d'),
        ], $newPhoto);

        if ($newPhoto !== null) {
            $this->storage->delete($oldPhoto);
        }

        return [
            'message' => 'Basic Info Updated Successful!',
            'data' => $this->settings->personalSummary($userId, $candidateId),
        ];
    }
}
