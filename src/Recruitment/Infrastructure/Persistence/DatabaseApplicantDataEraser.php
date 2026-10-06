<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ApplicantDataEraser;

final readonly class DatabaseApplicantDataEraser implements ApplicantDataEraser
{
    public function __construct(private ConnectionInterface $connection) {}

    public function resumePaths(int $userId): array
    {
        return array_map('strval', $this->connection->table('recruitment_applications')
            ->where('user_id', $userId)
            ->whereNotNull('resume_path')
            ->pluck('resume_path')
            ->all());
    }

    public function clearPersonalData(int $userId): void
    {
        $applications = $this->connection->table('recruitment_applications')->where('user_id', $userId);

        $this->connection->table('recruitment_application_answers')
            ->whereIn('application_id', (clone $applications)->select('id'))
            ->whereNotNull('answer_text')
            ->update(['answer_text' => null]);

        $applications->update([
            'cover_letter' => null,
            'withdrawn_note' => null,
            'resume_name' => null,
            'resume_path' => null,
            'resume_size_bytes' => null,
            'profile_snapshot' => null,
        ]);
    }
}
