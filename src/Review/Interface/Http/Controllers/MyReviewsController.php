<?php

namespace Src\Review\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Review\Application\DeleteReview;
use Src\Review\Application\EditReview;
use Src\Review\Application\GetMyReview;
use Src\Review\Application\ListMyReviews;
use Src\Review\Application\WriteReview;
use Src\Review\Interface\Http\Requests\ListMyReviewsRequest;
use Src\Review\Interface\Http\Requests\SaveReviewRequest;
use Src\Review\Interface\Http\Resources\MyReviewResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** A job seeker's own reviews: writing one for a company they applied to, reading, editing and deleting. A change answers with the whole review. */
final class MyReviewsController extends ApiController
{
    public function index(ListMyReviewsRequest $request, ActorResolver $actors, ListMyReviews $list): AnonymousResourceCollection
    {
        return MyReviewResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->pageRequest()), $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetMyReview $get): MyReviewResource
    {
        return new MyReviewResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(SaveReviewRequest $request, string $slug, ActorResolver $actors, WriteReview $write, GetMyReview $get): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $review = $write->execute($actor, $slug, $request->content());

        return (new MyReviewResource($get->execute($actor, (int) $review->id)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(SaveReviewRequest $request, int $id, ActorResolver $actors, EditReview $edit, GetMyReview $get): MyReviewResource
    {
        $actor = $actors->fromRequest($request);
        $edit->execute($actor, $id, $request->content());

        return new MyReviewResource($get->execute($actor, $id));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteReview $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
