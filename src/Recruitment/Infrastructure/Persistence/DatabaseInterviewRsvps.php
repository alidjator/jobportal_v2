<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\Rsvp;
use Src\Recruitment\Domain\RsvpAnswer;

final readonly class DatabaseInterviewRsvps implements InterviewRsvps
{
    private const string TIME = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function forInterviews(array $interviewIds): array
    {
        $answers = [];

        foreach ($this->connection->table('recruitment_interviews')->whereIn('id', $interviewIds)->get(['id', 'interviewer_id', 'interviewer_rsvp', 'interviewer_rsvp_note', 'interviewer_rsvp_at']) as $row) {
            $answers[(int) $row->id][(int) $row->interviewer_id] = $this->answerOf($row->interviewer_rsvp, $row->interviewer_rsvp_note, $row->interviewer_rsvp_at);
        }

        foreach ($this->connection->table('recruitment_interview_participants')->whereIn('interview_id', $interviewIds)->orderBy('id')->get(['interview_id', 'user_id', 'rsvp', 'rsvp_note', 'rsvp_at']) as $row) {
            $answers[(int) $row->interview_id][(int) $row->user_id] = $this->answerOf($row->rsvp, $row->rsvp_note, $row->rsvp_at);
        }

        return $answers;
    }

    public function countsForApplications(array $applicationIds): array
    {
        $interviews = $this->connection->table('recruitment_interviews')->whereIn('application_id', $applicationIds)->whereIn('status', ['pending', 'confirmed'])
            ->get(['id', 'application_id', 'interviewer_rsvp']);

        $counts = [];
        $applications = [];

        foreach ($interviews as $row) {
            $applications[(int) $row->id] = (int) $row->application_id;
            $counts[(int) $row->application_id] = ['pending' => 0, 'declined' => 0];
            $this->count($counts[(int) $row->application_id], (string) $row->interviewer_rsvp);
        }

        foreach ($this->connection->table('recruitment_interview_participants')->whereIn('interview_id', array_keys($applications))->get(['interview_id', 'rsvp']) as $row) {
            $this->count($counts[$applications[(int) $row->interview_id]], (string) $row->rsvp);
        }

        return $counts;
    }

    /** @param  array{pending: int, declined: int}  $counts */
    private function count(array &$counts, string $rsvp): void
    {
        if ($rsvp === Rsvp::Pending->value) {
            $counts['pending']++;
        } elseif ($rsvp === Rsvp::Declined->value) {
            $counts['declined']++;
        }
    }

    public function answer(int $interviewId, int $userId, Rsvp $rsvp, ?string $note, DateTimeImmutable $at): void
    {
        $isInterviewer = $this->connection->table('recruitment_interviews')->where('id', $interviewId)->where('interviewer_id', $userId)->exists();

        if ($isInterviewer) {
            $this->connection->table('recruitment_interviews')->where('id', $interviewId)
                ->update(['interviewer_rsvp' => $rsvp->value, 'interviewer_rsvp_note' => $note, 'interviewer_rsvp_at' => $this->format($at)]);

            return;
        }

        $this->connection->table('recruitment_interview_participants')->where('interview_id', $interviewId)->where('user_id', $userId)
            ->update(['rsvp' => $rsvp->value, 'rsvp_note' => $note, 'rsvp_at' => $this->format($at)]);
    }

    public function resetAll(int $interviewId): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interviewId)
            ->update(['interviewer_rsvp' => Rsvp::Pending->value, 'interviewer_rsvp_note' => null, 'interviewer_rsvp_at' => null]);
        $this->connection->table('recruitment_interview_participants')->where('interview_id', $interviewId)
            ->update(['rsvp' => Rsvp::Pending->value, 'rsvp_note' => null, 'rsvp_at' => null]);
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME);
    }

    private function answerOf(string $rsvp, ?string $note, ?string $at): RsvpAnswer
    {
        return new RsvpAnswer(Rsvp::from($rsvp), $note, $at === null ? null : new DateTimeImmutable($at, new DateTimeZone('UTC')));
    }
}
