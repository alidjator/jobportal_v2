<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Src\Recruitment\Application\CompareInterviewResults;
use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Interface\Http\Requests\CompareInterviewResultsRequest;
use Src\Recruitment\Interface\Http\Resources\InterviewComparisonRowResource;
use Src\Recruitment\Interface\Http\Resources\InterviewKitResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/**
 * The comparison of the interview results of one job's candidates (`docs/design/phase-8-interview-results.md` section 1). The kit that gives the columns comes with it, with
 * the anchors but without the guidance: whoever may read the comparison may read the anchors, the guidance stays for the interviewers.
 */
final class InterviewResultController extends ApiController
{
    public function index(CompareInterviewResultsRequest $request, int $id, ActorResolver $actors, CompareInterviewResults $compare): AnonymousResourceCollection
    {
        $comparison = $compare->execute($actors->fromRequest($request), $request->toQuery($id), $request->pageRequest());

        return InterviewComparisonRowResource::collection(PaginatedResources::paginator($comparison->rows, $request))
            ->additional(['kit' => $comparison->kit instanceof InterviewKit ? [
                'id' => $comparison->kit->id,
                'name' => $comparison->kit->name,
                /** @var list<array{position: int, text: string, weight: int, anchors: array{score_1: string|null, score_3: string|null, score_5: string|null}}> the columns of the comparison, in the order of `cells`; the anchors say what the scores 1, 3 and 5 mean */
                'questions' => array_map(static fn (array $question): array => Arr::except($question, 'guidance'), InterviewKitResource::questions($comparison->kit->questions)),
            ] : null]);
    }
}
