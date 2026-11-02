<?php

namespace Src\Assessment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Assessment\Application\Video\VideoTakes;
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

    public function attachmentPaths(int $userId): array
    {
        return array_map('strval', $this->connection->table('assessment_invitations')
            ->where('applicant_user_id', $userId)
            ->whereNotNull('attachment_path')
            ->pluck('attachment_path')
            ->all());
    }

    public function reportPdfPaths(int $userId): array
    {
        $reports = $this->connection->table('assessment_reports')->whereIn('attempt_id', $this->attemptIds($userId));
        $paths = (clone $reports)->whereNotNull('pdf_path')->pluck('pdf_path')->all();
        $revisions = $this->connection->table('assessment_report_revisions')->whereIn('report_id', (clone $reports)->select('id'))->whereNotNull('pdf_path')->pluck('pdf_path')->all();

        return array_values(array_unique(array_map('strval', [...$paths, ...$revisions])));
    }

    public function videoPaths(int $userId): array
    {
        $paths = [];

        foreach ($this->connection->table('assessment_video_takes')->whereIn('attempt_id', $this->attemptIds($userId))->whereIn('status', ['complete', 'recording'])->get() as $take) {
            if ($take->path !== null) {
                $paths[] = (string) $take->path;
            }

            for ($index = 0; $index < (int) $take->chunk_count; $index++) {
                $paths[] = VideoTakes::chunkPath((int) $take->id, $index);
            }
        }

        return $paths;
    }

    public function clearPersonalData(int $userId): void
    {
        $attempts = $this->attemptIds($userId);

        foreach (['assessment_answers', 'assessment_drawings', 'assessment_video_takes', 'assessment_speed_column_results', 'assessment_integrity_events'] as $table) {
            $this->connection->table($table)->whereIn('attempt_id', $attempts)->delete();
        }

        $this->connection->table('assessment_attempts')->whereIn('id', $attempts)->update(['last_ip' => null, 'session_hash' => null, 'norm_age' => null, 'norm_education_rank' => null]);
        $this->connection->table('assessment_invitations')->where('applicant_user_id', $userId)->update(['attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, 'attachment_size_bytes' => null]);
        $this->connection->table('assessment_results')->whereIn('attempt_id', $attempts)->update(['scale_scores' => null]);

        // The text, the explanation and the notes describe the person; the number, the signer and the time stay as the provider's register.
        $this->connection->table('assessment_reports')->whereIn('attempt_id', $attempts)->update(['body' => '', 'explanation' => null, 'review_notes' => null, 'pdf_path' => null, 'pdf_sha256' => null]);
        $this->connection->table('assessment_report_revisions')->whereIn('report_id', $this->connection->table('assessment_reports')->whereIn('attempt_id', $attempts)->select('id'))->update(['body' => '', 'explanation' => null, 'pdf_path' => null, 'pdf_sha256' => null]);
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
