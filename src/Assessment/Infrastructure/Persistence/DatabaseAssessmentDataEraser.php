<?php

namespace Src\Assessment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Assessment\Domain\AssessmentDataEraser;

final readonly class DatabaseAssessmentDataEraser implements AssessmentDataEraser
{
    public function __construct(private ConnectionInterface $connection) {}

    public function drawingPaths(int $userId): array
    {
        return array_map('strval', $this->connection->table('assessment_drawings')
            ->whereIn('attempt_id', $this->attemptIds($userId))
            ->pluck('path')
            ->all());
    }

    public function clearPersonalData(int $userId): void
    {
        $attempts = $this->attemptIds($userId);

        foreach (['assessment_answers', 'assessment_drawings', 'assessment_speed_column_results', 'assessment_integrity_events'] as $table) {
            $this->connection->table($table)->whereIn('attempt_id', $attempts)->delete();
        }

        $this->connection->table('assessment_attempts')->whereIn('id', $attempts)->update(['last_ip' => null]);
        $this->connection->table('assessment_results')->whereIn('attempt_id', $attempts)->update(['scale_scores' => null]);

        // The text, the explanation and the notes describe the person; the number, the signer and the time stay as the provider's register.
        $this->connection->table('assessment_reports')->whereIn('attempt_id', $attempts)->update(['body' => '', 'explanation' => null, 'review_notes' => null]);
    }

    /** @return Builder the ids of the attempts of the user's invitations, for use in a sub-select */
    private function attemptIds(int $userId): Builder
    {
        return $this->connection->table('assessment_attempts as attempts')
            ->join('assessment_invitations as invitations', 'invitations.id', '=', 'attempts.invitation_id')
            ->where('invitations.applicant_user_id', $userId)
            ->select('attempts.id');
    }
}
