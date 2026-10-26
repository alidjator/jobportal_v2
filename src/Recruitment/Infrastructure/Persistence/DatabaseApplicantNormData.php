<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Recruitment\ApplicantNormData;
use Src\Shared\Application\Recruitment\ApplicantNormFacts;

/** The age of the profile snapshot (the age on the day of applying) and the education rank of the application's facets. */
final readonly class DatabaseApplicantNormData implements ApplicantNormData
{
    public function __construct(private ConnectionInterface $connection) {}

    public function of(int $applicationId): ApplicantNormFacts
    {
        $snapshot = $this->connection->table('recruitment_applications')->where('id', $applicationId)->value('profile_snapshot');
        $decoded = is_string($snapshot) ? json_decode($snapshot, true) : null;
        $age = is_array($decoded) && isset($decoded['age']) && is_numeric($decoded['age']) ? (int) $decoded['age'] : null;
        $rank = $this->connection->table('recruitment_application_facets')->where('application_id', $applicationId)->value('education_rank');

        return new ApplicantNormFacts($age, $rank === null ? null : (int) $rank);
    }
}
