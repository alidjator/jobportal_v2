<?php

namespace Src\Recruitment\Application\Export;

use DateTimeImmutable;
use DateTimeInterface;
use Src\Recruitment\Application\ApplicantApplicationReader;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Application\ApplicationHistoryEntry;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Privacy\ExportedFile;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/**
 * The Recruitment part of the personal data export: every application as the job seeker sees it (stage history by system stage type, the reason code of a
 * rejection, the withdrawal note, the screening answers), the profile snapshot and resume copy they sent, their interviews and the offers made to them.
 * Left out, because it belongs to the company: stage names, notes, ratings, scorecards, the match with the screening rules, and the company's own rejection note.
 */
final readonly class ApplicationsDataExporter implements PersonalDataExporter
{
    public function __construct(
        private ApplicantApplicationReader $reader,
        private ApplicationRepository $applications,
        private InterviewRepository $interviews,
        private OfferRepository $offers,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
    ) {}

    public function key(): string
    {
        return 'applications';
    }

    public function export(int $userId): PersonalDataSection
    {
        $rows = [];
        $cursor = null;

        do {
            $page = $this->reader->list($userId, null, new CursorRequest($cursor, PageRequest::MAX_PER_PAGE));
            array_push($rows, ...$page->items);
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn ($row): int => $row->jobId, $rows))));
        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn ($row): int => $row->companyId, $rows))));

        $exported = [];
        $files = [];

        foreach ($rows as $row) {
            $detail = $this->reader->detail($userId, $row->id);
            $application = $this->applications->findOfApplicant($userId, $row->id);
            $resumeFile = null;

            if ($application?->resume !== null) {
                $resumeFile = 'application-'.$row->id.'-resume.pdf';
                $files[] = new ExportedFile($application->resume->path, $resumeFile);
            }

            $exported[] = [
                'job' => $titles[$row->jobId] ?? null,
                'company' => ($companies[$row->companyId] ?? null)?->name,
                'status' => $row->status->value,
                'applied_at' => $row->appliedAt->format(DateTimeInterface::ATOM),
                'cover_letter' => $detail?->coverLetter,
                'resume_name' => $detail?->resumeName,
                'resume_file' => $resumeFile === null ? null : 'files/applications/'.$resumeFile,
                'withdrawn_note' => $detail?->withdrawnNote,
                'history' => array_map(static fn (ApplicationHistoryEntry $entry): array => [
                    'status' => $entry->status->value,
                    'at' => $entry->at->format(DateTimeInterface::ATOM),
                    'by' => $entry->by->value,
                    'rejection_reason' => $entry->reason?->value,
                ], $detail->history ?? []),
                'screening_answers' => array_map(static fn (ApplicationAnswerView $answer): array => [
                    'question' => $answer->questionText,
                    'type' => $answer->type->value,
                    'text' => $answer->text,
                    'number' => $answer->number,
                    'yes_no' => $answer->bool,
                    'chosen_options' => array_column($answer->options, 'label'),
                ], $detail->answers ?? []),
                'profile_snapshot' => $application->profileSnapshot ?? [],
                'interviews' => array_map(static fn (Interview $interview): array => [
                    'starts_at' => $interview->startsAt->format(DateTimeInterface::ATOM),
                    'ends_at' => $interview->endsAt->format(DateTimeInterface::ATOM),
                    'format' => $interview->format->value,
                    'location' => $interview->location,
                    'meeting_url' => $interview->meetingUrl,
                    'status' => $interview->status->value,
                    'recorded' => $interview->recorded,
                    'recording_consent' => $interview->recordingConsent?->value,
                    'recording_consent_at' => self::time($interview->recordingConsentAt),
                    'your_phone' => $interview->candidatePhone,
                ], $this->interviews->forApplication($row->id)),
                'offers' => array_map(static fn (Offer $offer): array => [
                    'position' => $offer->position,
                    'salary' => $offer->salary,
                    'start_date' => $offer->startDate->format('Y-m-d'),
                    'note' => $offer->note,
                    'expires_at' => $offer->expiresAt->format(DateTimeInterface::ATOM),
                    'status' => $offer->status->value,
                    'your_answer_note' => $offer->answerNote,
                    'answered_at' => self::time($offer->answeredAt),
                ], $this->offers->forApplication($row->id)),
            ];
        }

        return new PersonalDataSection($exported, $files);
    }

    private static function time(?DateTimeImmutable $time): ?string
    {
        return $time?->format(DateTimeInterface::ATOM);
    }
}
