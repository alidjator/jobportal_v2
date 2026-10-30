<?php

namespace Src\Assessment\Application\Comparison;

use Src\Assessment\Application\Reports\ScaleSummaries;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\SubmittedAssessment;
use Src\Assessment\Domain\SubmittedAssessmentReader;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Recruitment\JobRoster;
use Src\Shared\Application\Recruitment\RosterEntry;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Compares the candidates of one job by the result of their latest submitted test (`docs/design/phase-8-assessment-automation.md` section 3), for the members who may see the
 * job's applications and hold `assessments.send`. The test whose sections (or scales) become the columns is the requested one or the test used most often among the rows. The rows
 * are sorted in memory (at most the applications of one job), only the page is read in full. An application without a submitted test is left out.
 */
final readonly class CompareAssessmentResults
{
    public function __construct(
        private JobRoster $roster,
        private SubmittedAssessmentReader $submitted,
        private TestRepository $tests,
        private SectionRepository $sections,
        private ScaleSummaries $scales,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the job is not visible to the member
     */
    public function execute(Actor $actor, AssessmentComparisonQuery $query, PageRequest $request): AssessmentComparison
    {
        $matchScores = $this->roster->applications($actor, $query->jobId, $query->stageType);
        $actor->membership?->require(CompanyPermission::AssessmentsSend);

        $latest = $this->submitted->latest(array_keys($matchScores), $query->stageId, $query->testId);
        $tests = $this->tests($latest);
        $chosen = $query->testId !== null && isset($tests[$query->testId]) ? $query->testId : $this->mostUsed($latest);
        $kind = $chosen === null ? null : $tests[$chosen]->kind();

        $ordered = $this->sorted($latest, $matchScores, $query->sort, $kind);
        $page = array_slice($ordered, $request->offset(), $request->perPage);
        $entries = $page === [] ? [] : $this->roster->entries($actor, $query->jobId, $page, $query->stageType);
        $titles = $chosen === null || $kind !== TestKind::Skill ? [] : $this->titles($chosen);
        $rows = [];

        foreach ($page as $id) {
            if (isset($entries[$id], $tests[$latest[$id]->testId])) {
                $rows[] = $this->row($entries[$id], $latest[$id], $tests[$latest[$id]->testId], $chosen, $titles);
            }
        }

        return new AssessmentComparison(
            $chosen === null ? null : ['id' => $chosen, 'name' => $tests[$chosen]->title(), 'kind' => $tests[$chosen]->kind()],
            $this->columns($chosen, $kind, $titles, $latest),
            new Page($rows, count($ordered), $request),
        );
    }

    /**
     * @param  array<int, SubmittedAssessment>  $latest
     * @return array<int, Test> by test id
     */
    private function tests(array $latest): array
    {
        $tests = [];

        foreach (array_unique(array_map(static fn (SubmittedAssessment $assessment): int => $assessment->testId, $latest)) as $testId) {
            $test = $this->tests->find($testId);

            if ($test instanceof Test) {
                $tests[$testId] = $test;
            }
        }

        return $tests;
    }

    /** @param  array<int, SubmittedAssessment>  $latest */
    private function mostUsed(array $latest): ?int
    {
        $counts = [];

        foreach ($latest as $assessment) {
            $counts[$assessment->testId] = ($counts[$assessment->testId] ?? 0) + 1;
        }

        // The most used first, the oldest test among equals.
        uksort($counts, static fn (int $a, int $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);

        return array_key_first($counts);
    }

    /**
     * The application ids that have a submitted test, highest first; a missing value comes last and ties keep the order of the roster. A battery has no percentage, so
     * the default sort of a battery is the time of submission.
     *
     * @param  array<int, SubmittedAssessment>  $latest
     * @param  array<int, int|null>  $matchScores
     * @return list<int>
     */
    private function sorted(array $latest, array $matchScores, string $sort, ?TestKind $kind): array
    {
        $sort = $sort === AssessmentComparisonQuery::SORT_PERCENTAGE && $kind === TestKind::Psychological ? AssessmentComparisonQuery::SORT_SUBMITTED_AT : $sort;
        $keys = [];

        foreach (array_keys($matchScores) as $position => $applicationId) {
            $assessment = $latest[$applicationId] ?? null;

            if ($assessment === null) {
                continue;
            }

            $value = match ($sort) {
                AssessmentComparisonQuery::SORT_SUBMITTED_AT => $assessment->submittedAt->getTimestamp(),
                AssessmentComparisonQuery::SORT_MATCH_SCORE => $matchScores[$applicationId],
                default => $assessment->percentage,
            };

            $keys[$applicationId] = [$value === null ? 1 : 0, -($value ?? 0), $position];
        }

        uasort($keys, static fn (array $a, array $b): int => $a <=> $b);

        return array_keys($keys);
    }

    /** @return array<int, string> the section titles of the test by section id, in order */
    private function titles(int $testId): array
    {
        $titles = [];

        foreach ($this->sections->ofTest($testId) as $section) {
            $titles[(int) $section->id] = $section->title;
        }

        return $titles;
    }

    /**
     * @param  array<int, string>  $titles
     * @param  array<int, SubmittedAssessment>  $latest
     * @return list<string>
     */
    private function columns(?int $chosen, ?TestKind $kind, array $titles, array $latest): array
    {
        if ($chosen === null) {
            return [];
        }

        if ($kind === TestKind::Skill) {
            return array_values($titles);
        }

        $scales = [];

        foreach ($latest as $assessment) {
            if ($assessment->testId === $chosen) {
                $scales = [...$scales, ...array_keys($assessment->scaleScores ?? [])];
            }
        }

        return array_values(array_unique(array_map('strval', $scales)));
    }

    /** @param  array<int, string>  $titles */
    private function row(RosterEntry $application, SubmittedAssessment $assessment, Test $test, ?int $chosen, array $titles): AssessmentComparisonRow
    {
        $kind = $test->kind();
        $compared = $assessment->testId === $chosen;
        $signed = $assessment->reportNumber !== null;

        $status = $kind === TestKind::Psychological
            ? ($signed ? 'signed' : 'awaiting_psychologist_review')
            : ($assessment->status?->value ?? 'pending_scoring');

        return new AssessmentComparisonRow(
            $application,
            $assessment,
            $test->title(),
            $kind,
            $status,
            $kind === TestKind::Skill && $compared ? $this->sectionCells($assessment, $titles) : null,
            $kind === TestKind::Psychological && $compared && $signed && $assessment->scaleScores !== null
                ? array_map(
                    static fn (array $scale): array => ['scale' => $scale['scale'], 'band' => $scale['band']],
                    $this->scales->rows($assessment->testId, $assessment->scaleScores, $assessment->candidate),
                )
                : null,
        );
    }

    /**
     * One cell per section of the test, in order: the share of the points earned (a speed section: the share of the attempted items that are correct), null when there is nothing to divide.
     *
     * @param  array<int, string>  $titles
     * @return list<array{title: string, percentage: float|null}>
     */
    private function sectionCells(SubmittedAssessment $assessment, array $titles): array
    {
        $scores = [];

        foreach ($assessment->sectionScores as $score) {
            $scores[(int) ($score['section_id'] ?? 0)] = $score;
        }

        $cells = [];

        foreach ($titles as $sectionId => $title) {
            $score = $scores[$sectionId] ?? [];
            [$part, $whole] = isset($score['max']) ? [(float) ($score['earned'] ?? 0), (float) $score['max']] : [(float) ($score['correct'] ?? 0), (float) ($score['attempted'] ?? 0)];

            $cells[] = ['title' => $title, 'percentage' => $whole > 0 ? round($part / $whole * 100, 1) : null];
        }

        return $cells;
    }
}
