<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\AnswerReview;
use Src\Recruitment\Application\CloseReviewRequest;
use Src\Recruitment\Application\CreateReviewRequest;
use Src\Recruitment\Application\GetReviewRequest;
use Src\Recruitment\Application\ListJobReviewRequests;
use Src\Recruitment\Application\ListMyReviewRequests;
use Src\Recruitment\Interface\Http\Requests\AnswerReviewRequest;
use Src\Recruitment\Interface\Http\Requests\CreateReviewRequestRequest;
use Src\Recruitment\Interface\Http\Requests\ListJobReviewRequestsRequest;
use Src\Recruitment\Interface\Http\Requests\ListMyReviewRequestsRequest;
use Src\Recruitment\Interface\Http\Resources\ReviewRequestResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Shortlist reviews: a recruiter asks the hiring managers of a job to review applications, and they answer. */
final class ReviewRequestController extends ApiController
{
    public function store(CreateReviewRequestRequest $request, int $id, ActorResolver $actors, CreateReviewRequest $create): JsonResponse
    {
        $view = $create->execute($actors->fromRequest($request), $id, $request->applicationIds(), $request->reviewerIds(), $request->dueOn(), $request->message());

        return (new ReviewRequestResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /** The review requests of a job, with every answer (the requester's view). */
    public function forJob(ListJobReviewRequestsRequest $request, int $id, ActorResolver $actors, ListJobReviewRequests $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $id, $request->pageRequest());

        return ReviewRequestResource::collection(PaginatedResources::paginator($page, $request));
    }

    /** The review requests where the caller is a reviewer, or that the caller made (`filter[role]=requester`). */
    public function mine(ListMyReviewRequestsRequest $request, ActorResolver $actors, ListMyReviewRequests $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->state(), $request->role(), $request->pageRequest());

        return ReviewRequestResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetReviewRequest $get): ReviewRequestResource
    {
        return new ReviewRequestResource($get->execute($actors->fromRequest($request), $id));
    }

    public function answer(AnswerReviewRequest $request, int $id, int $applicationId, ActorResolver $actors, AnswerReview $answer): ReviewRequestResource
    {
        return new ReviewRequestResource($answer->execute($actors->fromRequest($request), $id, $applicationId, $request->decision(), $request->reason()));
    }

    public function close(Request $request, int $id, ActorResolver $actors, CloseReviewRequest $close): Response
    {
        $close->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
