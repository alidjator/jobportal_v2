<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Assessment\Application\Comparison\CompareAssessmentResults;
use Src\Assessment\Interface\Http\Requests\CompareAssessmentResultsRequest;
use Src\Assessment\Interface\Http\Resources\AssessmentComparisonRowResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The comparison of the assessment results of one job's candidates (`docs/design/phase-8-assessment-automation.md` section 3). The test that gives the columns comes with it. */
final class AssessmentComparisonController extends ApiController
{
    public function index(CompareAssessmentResultsRequest $request, int $id, ActorResolver $actors, CompareAssessmentResults $compare): AnonymousResourceCollection
    {
        $comparison = $compare->execute($actors->fromRequest($request), $request->toQuery($id), $request->pageRequest());

        return AssessmentComparisonRowResource::collection(PaginatedResources::paginator($comparison->rows, $request))
            ->additional([
                /** @var array{id: int, name: string, kind: 'skill'|'psychological'}|null the test of the columns, null when no candidate has a submitted test */
                'test' => $comparison->test === null ? null : [...$comparison->test, 'kind' => $comparison->test['kind']->value],
                /** @var list<string> the columns, in the order of the cells: the titles of the sections of a skill test, the scales of a battery */
                'columns' => $comparison->columns,
            ]);
    }
}
