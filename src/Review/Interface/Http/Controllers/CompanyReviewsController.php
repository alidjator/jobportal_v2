<?php

namespace Src\Review\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Review\Application\DeleteReply;
use Src\Review\Application\ListCompanyReviews;
use Src\Review\Application\ListPublicReviews;
use Src\Review\Application\WriteReply;
use Src\Review\Interface\Http\Requests\ListReviewsRequest;
use Src\Review\Interface\Http\Requests\ReplyRequest;
use Src\Review\Interface\Http\Resources\CompanyReviewResource;
use Src\Review\Interface\Http\Resources\PublicReviewResource;
use Src\Review\Interface\Http\Resources\ReplyResource;
use Src\Review\Interface\Http\Resources\ReviewSummaryPayload;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The reviews on a company's public page, and the company side: the same reviews with its reply, writing and deleting the reply, */
final class CompanyReviewsController extends ApiController
{
    public function publicIndex(ListReviewsRequest $request, string $slug, ListPublicReviews $list): AnonymousResourceCollection
    {
        $result = $list->execute($slug, $request->sort(), $request->pageRequest());

        return PublicReviewResource::collection(PaginatedResources::paginator($result->page, $request))->additional(['summary' => ReviewSummaryPayload::of($result->summary)]);
    }

    public function index(ListReviewsRequest $request, ActorResolver $actors, ListCompanyReviews $list): AnonymousResourceCollection
    {
        $result = $list->execute($actors->fromRequest($request), $request->sort(), $request->pageRequest());

        return CompanyReviewResource::collection(PaginatedResources::paginator($result->page, $request))->additional(['summary' => ReviewSummaryPayload::of($result->summary)]);
    }

    public function reply(ReplyRequest $request, int $id, ActorResolver $actors, WriteReply $write): ReplyResource
    {
        return new ReplyResource($write->execute($actors->fromRequest($request), $id, $request->body()));
    }

    public function destroyReply(Request $request, int $id, ActorResolver $actors, DeleteReply $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
