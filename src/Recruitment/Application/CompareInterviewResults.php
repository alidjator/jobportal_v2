<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\KitQuestion;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Compares the candidates of one job by the result of their latest scored interview, for the members who may see the job's applications. The kit whose
 * questions become the columns is the requested one or the kit used most often among the rows. A row whose interview has the viewer on the panel without a
 * sent scorecard keeps its place but shows no result. The rows are sorted in memory (at most the applications of one job), only the page is read in full.
 */
final readonly class CompareInterviewResults
{
    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private InterviewResults $results,
        private InterviewPanel $panel,
        private InterviewKitLookup $kits,
        private JobInterviewStages $stages,
        private HiringManagerScope $scope,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the job, the stage or the kit is not visible to the member
     * @throws BusinessRuleViolation `stage-not-interview`
     */
    public function execute(Actor $actor, InterviewComparisonQuery $comparison, PageRequest $request): InterviewComparison
    {
        $member = $this->access->anyOf($actor, CompanyPermission::ApplicationsView, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $comparison->jobId);

        if ($comparison->stageId !== null) {
            $this->stages->find($member, $comparison->stageId, 'Only an interview stage can be compared.');
        }

        $kit = $comparison->kitId === null ? null : $this->kits->find($member, $comparison->kitId);

        $query = (new RecruiterApplicationQuery($comparison->jobId, $comparison->stageId, $comparison->stageType, activeOnly: true))
            ->withHiringManagerScope($this->scope->isBound($member, $comparison->jobId));

        $matchScores = $this->reader->matchScores($query);
        $latest = $this->results->latestInterviews(array_keys($matchScores), $member->userId);
        $kit ??= $this->mostUsedKit($member, $latest);

        $ordered = $this->sorted($latest, $matchScores, $comparison->sort);
        $pageIds = array_slice($ordered, $request->offset(), $request->perPage);

        return new InterviewComparison($kit, new Page($this->rows($pageIds, $latest, $query, $kit), count($ordered), $request));
    }

    /** @param  array<int, LatestInterview>  $latest */
    private function mostUsedKit(RecruitmentMember $member, array $latest): ?InterviewKit
    {
        $counts = [];

        foreach ($latest as $interview) {
            if ($interview->kitId !== null) {
                $counts[$interview->kitId] = ($counts[$interview->kitId] ?? 0) + 1;
            }
        }

        // The most used first, the oldest kit among equals; a kit deleted since is skipped.
        uksort($counts, static fn (int $a, int $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);

        foreach (array_keys($counts) as $kitId) {
            try {
                return $this->kits->find($member, $kitId);
            } catch (EntityNotFound) {
                continue;
            }
        }

        return null;
    }

    /**
     * The application ids with a scored interview, highest first; a missing value (a locked row, an unscored interview or no match score) comes last, and ties
     * keep the order of the list.
     *
     * @param  array<int, LatestInterview>  $latest
     * @param  array<int, int|null>  $matchScores
     * @return list<int>
     */
    private function sorted(array $latest, array $matchScores, string $sort): array
    {
        $keys = [];

        foreach (array_keys($matchScores) as $position => $applicationId) {
            $interview = $latest[$applicationId] ?? null;

            if ($interview === null) {
                continue;
            }

            $value = match ($sort) {
                InterviewComparisonQuery::SORT_YES => $interview->result?->yes(),
                InterviewComparisonQuery::SORT_MATCH_SCORE => $matchScores[$applicationId],
                default => $interview->result?->score,
            };

            $keys[$applicationId] = [$value === null ? 1 : 0, -($value ?? 0), $position];
        }

        uasort($keys, static fn (array $a, array $b): int => $a <=> $b);

        return array_keys($keys);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, LatestInterview>  $latest
     * @return list<InterviewComparisonRow>
     */
    private function rows(array $ids, array $latest, RecruiterApplicationQuery $query, ?InterviewKit $kit): array
    {
        $applications = $this->reader->rows($ids, $query);
        $interviewIds = array_map(static fn (int $id): int => $latest[$id]->interviewId, $ids);
        $panels = $this->panel->forInterviews($interviewIds);
        $visible = [];

        foreach ($ids as $id) {
            if (! $latest[$id]->locked && $kit instanceof InterviewKit && $latest[$id]->kitId === $kit->id) {
                $visible[] = $latest[$id]->interviewId;
            }
        }

        $scores = $this->results->questionScores($visible);
        $rows = [];

        foreach ($ids as $id) {
            if (! isset($applications[$id])) {
                continue;
            }

            $interview = $latest[$id];
            $rows[] = new InterviewComparisonRow(
                $applications[$id],
                $interview,
                1 + count($panels[$interview->interviewId] ?? []),
                $interview->locked || ! $kit instanceof InterviewKit ? null : $this->cells($kit, $interview->kitId === $kit->id ? ($scores[$interview->interviewId] ?? []) : []),
            );
        }

        return $rows;
    }

    /**
     * One cell per question of the kit: the interview's question with the same text (ignoring case and the space around it) that is not used by an earlier column.
     *
     * @param  list<QuestionScore>  $scores
     * @return list<QuestionScore>
     */
    private function cells(InterviewKit $kit, array $scores): array
    {
        $byText = [];

        foreach ($scores as $score) {
            $byText[mb_strtolower(trim($score->text))][] = $score;
        }

        return array_map(static function (KitQuestion $question) use (&$byText): QuestionScore {
            $key = mb_strtolower(trim($question->text));
            $match = isset($byText[$key]) ? array_shift($byText[$key]) : null;

            return new QuestionScore($question->text, $match?->average, $match->count ?? 0);
        }, $kit->questions);
    }
}
