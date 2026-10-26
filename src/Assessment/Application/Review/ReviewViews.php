<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Application\Reports\ScaleSummaries;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\IntegrityTier;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\RequestContext;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Builds the review of a report from the attempt behind it, and works out what still stands between the psychologist and the signature. */
final readonly class ReviewViews
{
    public const string LICENCE_INVALID = 'licence-invalid';

    public const string EXPLANATION_REQUIRED = 'explanation-required';

    public const string DRAWINGS_UNSCORED = 'drawings-unscored';

    public const string SCALES_NOT_NORMED = 'scales-not-normed';

    public function __construct(
        private AttemptRepository $attempts,
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private AttemptOutcomeRepository $outcomes,
        private AnswerRepository $answers,
        private QuestionRepository $questions,
        private ScaleSummaries $scales,
        private ApplicantContacts $accounts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private ReferenceValues $references,
        private RequestContext $request,
        private ReviewClaims $claims,
        private Clock $clock,
    ) {}

    public function build(Psychologist $psychologist, Report $report): ReviewView
    {
        $attempt = $this->attempts->find($report->attemptId) ?? throw EntityNotFound::of('Attempt', $report->attemptId);
        $invitation = $this->invitations->find($attempt->invitationId) ?? throw EntityNotFound::of('Invitation', $attempt->invitationId);
        $test = $this->tests->find($invitation->testId) ?? throw EntityNotFound::of('Test', $invitation->testId);
        $result = $this->outcomes->resultOf($report->attemptId);

        $scales = $result?->scaleScores === null ? [] : $this->scales->rows($invitation->testId, $result->scaleScores, $attempt->normCandidate());
        $drawings = $this->drawings($report->attemptId);

        return new ReviewView(
            $report,
            (int) $invitation->id,
            $this->accounts->names([$invitation->applicantUserId])[$invitation->applicantUserId] ?? '',
            $test->title(),
            $attempt->submittedAt,
            $result->integrityTier ?? IntegrityTier::None,
            $scales,
            $this->answers->columnsOf($report->attemptId),
            $drawings,
            $report->isSigned() ? [] : $this->problems($psychologist, $report, $scales, $drawings),
            $this->context($attempt, $invitation),
            $this->claims->holder($psychologist, $report),
        );
    }

    /** @return array{id: int, name: string, mine: bool}|null */
    public function holderOf(Psychologist $viewer, Report $report): ?array
    {
        return $this->claims->holder($viewer, $report);
    }

    /**
     * The age and the education the candidate had when they applied (the copy on the attempt), the job and the company. For the psychologist only: the company never sees the age
     * of a candidate of a psychological test, and none of it reaches the PDF unless the provider's template asks for the age or the job.
     *
     * @return array{age: int|null, education_level: array{rank: int, name: string|null}|null, job: array{title: string, level: string|null, category: string|null}|null, company_name: string|null}
     */
    private function context(Attempt $attempt, Invitation $invitation): array
    {
        $language = $this->request->language;
        $job = $this->jobs->classification($invitation->jobId, $language);

        return [
            'age' => $attempt->normAge,
            'education_level' => $attempt->normEducationRank === null ? null : ['rank' => $attempt->normEducationRank, 'name' => $this->references->educationLevelName($attempt->normEducationRank, $language)],
            'job' => $job === null ? null : ['title' => $job->title, 'level' => $job->level, 'category' => $job->category],
            'company_name' => $this->companies->summaries([$invitation->companyId])[$invitation->companyId]->name ?? null,
        ];
    }

    /** @return list<array{question_id: int, text: string, max_score: int, score: float|null, note: string|null, mime_type: string}> */
    private function drawings(int $attemptId): array
    {
        $drawings = $this->answers->drawingsOf($attemptId);
        $questions = $this->questions->manyById(array_keys($drawings));
        $rows = [];

        foreach ($drawings as $questionId => $drawing) {
            $question = $questions[$questionId] ?? null;

            $rows[] = [
                'question_id' => $questionId,
                'text' => $question->text ?? '',
                'max_score' => $question->points ?? 0,
                'score' => $drawing->score,
                'note' => $drawing->note,
                'mime_type' => $drawing->mimeType,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{scale: string, raw: float, standard: float|null, band: string|null, norm_group: array{age_min: int|null, age_max: int|null, education_rank_min: int|null, education_rank_max: int|null}|null}>  $scales
     * @param  list<array{question_id: int, text: string, max_score: int, score: float|null, note: string|null, mime_type: string}>  $drawings
     * @return list<string>
     */
    private function problems(Psychologist $psychologist, Report $report, array $scales, array $drawings): array
    {
        $problems = [];

        if (! $psychologist->canSignReports($this->clock->now())) {
            $problems[] = self::LICENCE_INVALID;
        }

        if (trim((string) $report->explanation) === '') {
            $problems[] = self::EXPLANATION_REQUIRED;
        }

        if (array_filter($drawings, static fn (array $drawing): bool => $drawing['score'] === null) !== []) {
            $problems[] = self::DRAWINGS_UNSCORED;
        }

        if (array_filter($scales, static fn (array $row): bool => $row['standard'] === null) !== []) {
            $problems[] = self::SCALES_NOT_NORMED;
        }

        return $problems;
    }
}
