<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\ApplicantsExported;
use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The applicants of one job as text cells, for the members who may see the job (the access of the pipeline list), at most `recruitment.max_export_rows` of them: a larger selection is refused. The rows are streamed, not collected. The export is audited before it is handed
 * out: the audit listener is not queued, so when the entry cannot be written the export fails and nothing is sent.
 */
final readonly class ExportJobApplications
{
    private const array HEADERS = [
        'Application ID', 'Applied at', 'Name', 'Email', 'Phone', 'City', 'Age', 'Headline', 'Latest job title', 'Latest job company',
        'Expected salary (IDR)', 'Expected salary period', 'Stage', 'Meets must-have', 'Average rating', 'Rating count',
        'Interview score', 'Interview yes', 'Interview no', 'Interview kit',
    ];

    /** How many applicants are read before their interview results are fetched in one query. */
    private const int RESULT_CHUNK = 200;

    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private JobQuestionRepository $questions,
        private EventDispatcher $events,
        private Clock $clock,
        private Settings $settings,
        private ContactVisibility $contact,
        private HiringManagerScope $scope,
        private InterviewResults $results,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws BusinessRuleViolation `export-too-large`
     */
    public function execute(Actor $actor, RecruiterApplicationQuery $query, ?string $ipAddress, ?string $userAgent): ApplicantExport
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsExport);
        $jobId = (int) $query->jobId;
        $this->access->job($member, $jobId);
        $query = $query->withHiringManagerScope($this->scope->isBound($member, $jobId));

        $cap = $this->settings->integer(RecruitmentSettings::MAX_EXPORT_ROWS, $member->companyId);
        $total = $this->reader->count($query);

        if ($total > $cap) {
            throw new BusinessRuleViolation('An export can hold at most :max applications; narrow the filters.', 'export-too-large', extensions: ['max_rows' => $cap], parameters: ['max' => $cap]);
        }

        $questions = $this->questions->forJob($jobId);
        $now = $this->clock->now();

        $this->events->dispatch([new ApplicantsExported($jobId, $member->companyId, $member->userId, $this->filters($query), $total, $ipAddress, $userAgent, $now)]);

        return new ApplicantExport($jobId, $now, [...self::HEADERS, ...array_map(static fn ($question): string => $question->question->text, $questions)], $this->rows($query, $total, $questions, $this->contact->companyIsOpen($member->companyId), $member->userId));
    }

    /**
     * @param  list<JobQuestion>  $questions
     * @return iterable<list<string>>
     */
    private function rows(RecruiterApplicationQuery $query, int $limit, array $questions, bool $companyIsOpen, int $viewerId): iterable
    {
        $chunk = [];

        foreach ($this->reader->export($query, $limit) as $applicant) {
            $chunk[] = $applicant;

            if (count($chunk) === self::RESULT_CHUNK) {
                yield from $this->chunkRows($chunk, $questions, $companyIsOpen, $viewerId);
                $chunk = [];
            }
        }

        yield from $this->chunkRows($chunk, $questions, $companyIsOpen, $viewerId);
    }

    /**
     * @param  list<ApplicantExportRow>  $applicants
     * @param  list<JobQuestion>  $questions
     * @return iterable<list<string>>
     */
    private function chunkRows(array $applicants, array $questions, bool $companyIsOpen, int $viewerId): iterable
    {
        $interviews = $this->results->latestInterviews(array_map(static fn (ApplicantExportRow $applicant): int => $applicant->row->id, $applicants), $viewerId);

        foreach ($applicants as $applicant) {
            yield $this->cells($applicant, $questions, $this->contact->closedFor($companyIsOpen, $applicant->row->stageType), $interviews[$applicant->row->id] ?? null);
        }
    }

    /**
     * @param  list<JobQuestion>  $questions
     * @return list<string>
     */
    private function cells(ApplicantExportRow $applicant, array $questions, bool $contactClosed, ?LatestInterview $interview): array
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
            $contactClosed ? '' : (string) $applicant->email,
            $contactClosed ? '' : (string) $applicant->phone,
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
            ...self::interviewCells($interview),
            ...array_map(static fn ($question): string => $answers[$question->question->id] ?? '', $questions),
        ];
    }

    /**
     * The score of the latest scored interview, the yes and the no recommendations and the kit; empty without one, and for an interview whose result is locked for
     * the exporting member (they are on its panel and have not sent their scorecard).
     *
     * @return list<string>
     */
    private static function interviewCells(?LatestInterview $interview): array
    {
        $result = $interview?->result;

        if (! $result instanceof InterviewResult) {
            return ['', '', '', ''];
        }

        return [$result->score === null ? '' : number_format($result->score, 2, '.', ''), (string) $result->yes(), (string) $result->no(), (string) $interview->kitName];
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
