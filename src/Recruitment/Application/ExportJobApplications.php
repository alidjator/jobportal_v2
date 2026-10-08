<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\ApplicantsExported;
use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The applicants of one job as text cells, for the members who may see the job (the access of the pipeline list). The export is audited before it is handed
 * out: the audit listener is not queued, so when the entry cannot be written the export fails and nothing is sent.
 */
final readonly class ExportJobApplications
{
    private const array HEADERS = [
        'Application ID', 'Applied at', 'Name', 'Email', 'Phone', 'City', 'Age', 'Headline', 'Latest job title', 'Latest job company',
        'Expected salary (IDR)', 'Expected salary period', 'Stage', 'Meets must-have', 'Average rating', 'Rating count',
    ];

    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private JobQuestionRepository $questions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, RecruiterApplicationQuery $query, ?string $ipAddress, ?string $userAgent): ApplicantExport
    {
        $member = $this->access->member($actor);
        $this->access->job($member, $query->jobId);

        $questions = $this->questions->forJob($query->jobId);
        $rows = [];

        foreach ($this->reader->export($query) as $applicant) {
            $rows[] = $this->cells($applicant, $questions);
        }

        $now = $this->clock->now();

        $this->events->dispatch([new ApplicantsExported($query->jobId, $member->companyId, $member->userId, $this->filters($query), count($rows), $ipAddress, $userAgent, $now)]);

        return new ApplicantExport($query->jobId, $now, [...self::HEADERS, ...array_map(static fn ($question): string => $question->question->text, $questions)], $rows);
    }

    /**
     * @param  list<JobQuestion>  $questions
     * @return list<string>
     */
    private function cells(ApplicantExportRow $applicant, array $questions): array
    {
        $row = $applicant->row;
        $answers = [];

        foreach ($applicant->answers as $answer) {
            $answers[$answer->questionId] = match (true) {
                $answer->options !== [] => implode('; ', array_map(static fn (array $option): string => $option['label'], $answer->options)),
                $answer->bool !== null => self::yesNo($answer->bool),
                $answer->number !== null => (string) $answer->number,
                default => (string) $answer->text,
            };
        }

        // An anonymised applicant keeps a row for the count, with every personal column empty.
        $personal = $row->isDeleted ? array_fill(0, 9, '') : [
            (string) $row->name,
            (string) $applicant->email,
            (string) $applicant->phone,
            (string) $row->city,
            (string) $row->age,
            (string) $row->headline,
            (string) ($row->latestExperience['title'] ?? ''),
            (string) ($row->latestExperience['company'] ?? ''),
            $row->expectedSalary === null ? '' : (string) $row->expectedSalary['amount'],
        ];

        if ($row->isDeleted) {
            $personal[0] = 'Deleted account';
        }

        return [
            (string) $row->id,
            $row->appliedAt->format('Y-m-d\TH:i:s\Z'),
            ...$personal,
            (string) ($row->expectedSalary['period']['name']['en'] ?? ''),
            $row->stageName,
            $row->meetsMustHave === null ? '' : self::yesNo($row->meetsMustHave),
            $row->ratingAverage === null ? '' : (string) $row->ratingAverage,
            (string) $row->ratingCount,
            ...array_map(static fn ($question): string => $answers[$question->question->id] ?? '', $questions),
        ];
    }

    /** @return array<string, bool|int|string> */
    private function filters(RecruiterApplicationQuery $query): array
    {
        return array_filter([
            'stage_id' => $query->stageId,
            'stage_type' => $query->stageType?->value,
            'meets_must_have' => $query->meetsMustHave,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private static function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
