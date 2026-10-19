<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Recruitment\Application\CompareInterviewResults;
use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Interface\Http\Requests\CompareInterviewResultsRequest;
use Src\Recruitment\Interface\Http\Resources\InterviewComparisonRowResource;
use Src\Recruitment\Interface\Http\Resources\InterviewKitResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The comparison of the interview results of one job's candidates (`docs/design/phase-8-interview-results.md` section 1). */
final class InterviewResultController extends ApiController
{
    public function index(CompareInterviewResultsRequest $request, int $id, ActorResolver $actors, CompareInterviewResults $compare): AnonymousResourceCollection
    {
        $comparison = $compare->execute($actors->fromRequest($request), $request->toQuery($id), $request->pageRequest());

        return InterviewComparisonRowResource::collection(PaginatedResources::paginator($comparison->rows, $request))
            ->additional(['kit' => $comparison->kit instanceof InterviewKit ? ['id' => $comparison->kit->id, 'name' => $comparison->kit->name, 'questions' => InterviewKitResource::questions($comparison->kit->questions)] : null]);
    }
}
