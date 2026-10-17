<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
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

    public function recordingPaths(int $userId): array
    {
        return array_map('strval', $this->connection->table('recruitment_interview_recordings')
            ->whereIn('interview_id', $this->interviewIds($userId))
            ->whereNotNull('path')
            ->pluck('path')
            ->all());
    }

    public function clearPersonalData(int $userId): void
    {
        $applications = $this->connection->table('recruitment_applications')->where('user_id', $userId);

        $this->connection->table('recruitment_application_answers')
            ->whereIn('application_id', (clone $applications)->select('id'))
            ->whereNotNull('answer_text')
            ->update(['answer_text' => null]);

        // The team's free note of a rejection or of a withdrawal it marked sits on the history entry and may name the applicant; the reason codes stay (they are not personal text).
        $this->connection->table('recruitment_application_stage_history')
            ->whereIn('application_id', (clone $applications)->select('id'))
            ->whereNotNull('note')
            ->update(['note' => null]);

        // Notes may quote the applicant, so they go with the rest of the personal content; the ratings hold no personal data and stay.
        $this->connection->table('recruitment_application_notes')
            ->whereIn('application_id', (clone $applications)->select('id'))
            ->delete();

        // Recordings and attendance go with the applicant even when the interview stays for its scorecard (the files were deleted before).
        $this->connection->table('recruitment_interview_recordings')->whereIn('interview_id', $this->interviewIds($userId))->delete();
        $this->connection->table('recruitment_interview_attendances')->whereIn('interview_id', $this->interviewIds($userId))->delete();

        // Interviews hold the applicant's meeting details, so they go too. An interview with a scorecard stays with its scores and recommendations (they hold no
        // personal data, like ratings) and loses only the meeting details; the scorecard notes are cleared by their own step (`EraseScorecardData`).
        $interviews = $this->connection->table('recruitment_interviews')->whereIn('application_id', (clone $applications)->select('id'));

        (clone $interviews)->whereNotIn('id', $this->connection->table('recruitment_interview_scorecards')->select('interview_id'))->delete();
        $interviews->update(['location' => null, 'meeting_url' => null]);

        // Offers carry the applicant's answer note, so they go too.
        $this->connection->table('recruitment_offers')
            ->whereIn('application_id', (clone $applications)->select('id'))
            ->delete();

        $applications->update([
            'cover_letter' => null,
            'withdrawn_note' => null,
            'resume_name' => null,
            'resume_path' => null,
            'resume_size_bytes' => null,
            'profile_snapshot' => null,
        ]);
    }

    private function interviewIds(int $userId): Builder
    {
        return $this->connection->table('recruitment_interviews')
            ->select('id')
            ->whereIn('application_id', $this->connection->table('recruitment_applications')->select('id')->where('user_id', $userId));
    }
}
