<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\RecordingConsent;
use stdClass;

final readonly class DatabaseInterviewRepository implements InterviewRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function findById(int $interviewId): ?Interview
    {
        $row = $this->connection->table('recruitment_interviews')->where('id', $interviewId)->first();

        return $row === null ? null : $this->toInterview($row);
    }

    public function active(int $applicationId, bool $forUpdate = false): ?Interview
    {
        $query = $this->connection->table('recruitment_interviews')->where('active_key', $applicationId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toInterview($row);
    }

    public function forApplication(int $applicationId): array
    {
        return $this->connection->table('recruitment_interviews')
            ->where('application_id', $applicationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (stdClass $row): Interview => $this->toInterview($row))
            ->all();
    }

    public function add(Interview $interview): Interview
    {
        $at = $this->format($interview->createdAt);

        $id = (int) $this->connection->table('recruitment_interviews')->insertGetId([
            'application_id' => $interview->applicationId,
            'starts_at' => $this->format($interview->startsAt),
            'ends_at' => $this->format($interview->endsAt),
            'format' => $interview->format->value,
            'location' => $interview->location,
            'meeting_url' => $interview->meetingUrl,
            'interviewer_id' => $interview->interviewerId,
            'created_by' => $interview->createdBy,
            'status' => $interview->status->value,
            'proposals_used' => $interview->proposalsUsed,
            'proposed_starts_at' => $interview->proposedStartsAt === null ? null : $this->format($interview->proposedStartsAt),
            'active_key' => $interview->status->isActive() ? $interview->applicationId : null,
            'kit_id' => $interview->kitId,
            'kit_name' => $interview->kitName,
            'recorded' => $interview->recorded,
            'candidate_phone' => $interview->candidatePhone,
            'note' => $interview->note,
            'calendar_uid' => $interview->calendarUid,
            'calendar_sequence' => $interview->calendarSequence,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return new Interview(
            $interview->applicationId,
            $interview->startsAt,
            $interview->endsAt,
            $interview->format,
            $interview->location,
            $interview->meetingUrl,
            $interview->interviewerId,
            $interview->createdBy,
            $interview->status,
            $interview->proposalsUsed,
            $interview->proposedStartsAt,
            $interview->createdAt,
            null,
            $interview->kitId,
            $interview->kitName,
            $interview->recorded,
            $interview->recordingConsent,
            $interview->recordingConsentAt,
            $interview->candidatePhone,
            $id,
            $interview->note,
            $interview->calendarUid,
            $interview->calendarSequence,
        );
    }

    public function update(Interview $interview): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interview->id)->update([
            'starts_at' => $this->format($interview->startsAt),
            'ends_at' => $this->format($interview->endsAt),
            'status' => $interview->status->value,
            'proposals_used' => $interview->proposalsUsed,
            'proposed_starts_at' => $interview->proposedStartsAt === null ? null : $this->format($interview->proposedStartsAt),
            'active_key' => $interview->status->isActive() ? $interview->applicationId : null,
            'reminded_at' => $interview->remindedAt === null ? null : $this->format($interview->remindedAt),
            'recording_consent' => $interview->recordingConsent?->value,
            'recording_consent_at' => $interview->recordingConsentAt === null ? null : $this->format($interview->recordingConsentAt),
            'candidate_phone' => $interview->candidatePhone,
            'note' => $interview->note,
            'calendar_sequence' => $interview->calendarSequence,
            'updated_at' => $this->format(new DateTimeImmutable),
        ]);
    }

    public function calendarChain(int $applicationId): ?array
    {
        $row = $this->connection->table('recruitment_interviews')->where('application_id', $applicationId)
            ->selectRaw('MIN(calendar_uid) as uid, MAX(calendar_sequence) as sequence')->first();

        return $row === null || $row->uid === null ? null : ['uid' => (string) $row->uid, 'sequence' => (int) $row->sequence];
    }

    public function cancelActive(int $applicationId): ?Interview
    {
        $active = $this->active($applicationId, forUpdate: true);

        if ($active === null) {
            return null;
        }

        $cancelled = $active->cancel();
        $this->update($cancelled);

        return $cancelled;
    }

    public function busyRanges(int $interviewerId, DateTimeImmutable $from, array $exceptApplicationIds = []): array
    {
        return $this->connection->table('recruitment_interviews')
            ->where('interviewer_id', $interviewerId)
            ->whereIn('status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])
            ->where('ends_at', '>', $this->format($from))
            ->whereNotIn('application_id', $exceptApplicationIds)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at'])
            ->map(fn (stdClass $row): array => [$this->time((string) $row->starts_at), $this->time((string) $row->ends_at)])
            ->all();
    }

    public function dueForReminder(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->connection->table('recruitment_interviews')
            ->where('status', InterviewStatus::Confirmed->value)
            ->whereNull('reminded_at')
            ->where('starts_at', '>', $this->format($from))
            ->where('starts_at', '<=', $this->format($until))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (stdClass $row): Interview => $this->toInterview($row))
            ->all();
    }

    public function dueForScorecardReminder(DateTimeImmutable $endedBefore, DateTimeImmutable $endedAfter): array
    {
        return $this->connection->table('recruitment_interviews')
            ->whereIn('status', [InterviewStatus::Confirmed->value, InterviewStatus::Completed->value])
            ->whereNull('scorecard_reminded_at')
            ->where('ends_at', '<=', $this->format($endedBefore))
            ->where('ends_at', '>', $this->format($endedAfter))
            ->orderBy('ends_at')
            ->get()
            ->map(fn (stdClass $row): Interview => $this->toInterview($row))
            ->all();
    }

    public function claimScorecardReminder(int $interviewId, DateTimeImmutable $at): bool
    {
        return $this->connection->table('recruitment_interviews')->where('id', $interviewId)->whereNull('scorecard_reminded_at')->update(['scorecard_reminded_at' => $this->format($at)]) > 0;
    }

    private function toInterview(stdClass $row): Interview
    {
        return new Interview(
            (int) $row->application_id,
            $this->time((string) $row->starts_at),
            $this->time((string) $row->ends_at),
            InterviewFormat::from((string) $row->format),
            $row->location === null ? null : (string) $row->location,
            $row->meeting_url === null ? null : (string) $row->meeting_url,
            (int) $row->interviewer_id,
            (int) $row->created_by,
            InterviewStatus::from((string) $row->status),
            (int) $row->proposals_used,
            $row->proposed_starts_at === null ? null : $this->time((string) $row->proposed_starts_at),
            $this->time((string) $row->created_at),
            $row->reminded_at === null ? null : $this->time((string) $row->reminded_at),
            $row->kit_id === null ? null : (int) $row->kit_id,
            $row->kit_name === null ? null : (string) $row->kit_name,
            (bool) $row->recorded,
            $row->recording_consent === null ? null : RecordingConsent::from((string) $row->recording_consent),
            $row->recording_consent_at === null ? null : $this->time((string) $row->recording_consent_at),
            $row->candidate_phone === null ? null : (string) $row->candidate_phone,
            (int) $row->id,
            $row->note === null ? null : (string) $row->note,
            (string) $row->calendar_uid,
            (int) $row->calendar_sequence,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
