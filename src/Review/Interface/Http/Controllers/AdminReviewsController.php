<?php

namespace Src\Review\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Review\Application\ModerateReply;
use Src\Review\Application\ModerateReview;
use Src\Review\Application\ReviewModeration;
use Src\Review\Interface\Http\Requests\ListAdminRepliesRequest;
use Src\Review\Interface\Http\Requests\ListAdminReportsRequest;
use Src\Review\Interface\Http\Requests\ListAdminReviewsRequest;
use Src\Review\Interface\Http\Requests\ModerationRequest;
use Src\Review\Interface\Http\Resources\AdminReviewDetailResource;
use Src\Review\Interface\Http\Resources\AdminReviewResource;
use Src\Review\Interface\Http\Resources\ReplyItemResource;
use Src\Review\Interface\Http\Resources\ReportItemResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The moderator's side (`admin/...`, permission `reviews.moderate`): the queues of reviews, replies and reports, and the decisions. A decision on a review answers with the whole review. */
final class AdminReviewsController extends ApiController
{
    public function index(ListAdminReviewsRequest $request, ActorResolver $actors, ReviewModeration $moderation): AnonymousResourceCollection
    {
        return AdminReviewResource::collection(PaginatedResources::paginator($moderation->reviews($actors->fromRequest($request), $request->reviewQuery(), $request->pageRequest()), $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, ReviewModeration $moderation): AdminReviewDetailResource
    {
        return new AdminReviewDetailResource($moderation->review($actors->fromRequest($request), $id));
    }

    public function approve(Request $request, int $id, ActorResolver $actors, ModerateReview $moderate, ReviewModeration $moderation): AdminReviewDetailResource
    {
        $actor = $actors->fromRequest($request);
        $moderate->approve($actor, $id, $request->ip(), $request->userAgent());

        return new AdminReviewDetailResource($moderation->review($actor, $id));
    }

    public function reject(ModerationRequest $request, int $id, ActorResolver $actors, ModerateReview $moderate, ReviewModeration $moderation): AdminReviewDetailResource
    {
        $actor = $actors->fromRequest($request);
        $moderate->reject($actor, $id, $request->reason(), $request->note(), $request->ip(), $request->userAgent());

        return new AdminReviewDetailResource($moderation->review($actor, $id));
    }

    public function remove(ModerationRequest $request, int $id, ActorResolver $actors, ModerateReview $moderate, ReviewModeration $moderation): AdminReviewDetailResource
    {
        $actor = $actors->fromRequest($request);
        $moderate->remove($actor, $id, $request->reason(), $request->note(), $request->ip(), $request->userAgent());

        return new AdminReviewDetailResource($moderation->review($actor, $id));
    }

    public function replies(ListAdminRepliesRequest $request, ActorResolver $actors, ReviewModeration $moderation): AnonymousResourceCollection
    {
        return ReplyItemResource::collection(PaginatedResources::paginator($moderation->replies($actors->fromRequest($request), $request->status(), $request->pageRequest()), $request));
    }

    public function approveReply(Request $request, int $reviewId, ActorResolver $actors, ModerateReply $moderate): Response
    {
        $moderate->approve($actors->fromRequest($request), $reviewId, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function rejectReply(ModerationRequest $request, int $reviewId, ActorResolver $actors, ModerateReply $moderate): Response
    {
        $moderate->reject($actors->fromRequest($request), $reviewId, $request->reason(), $request->note(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function removeReply(ModerationRequest $request, int $reviewId, ActorResolver $actors, ModerateReply $moderate): Response
    {
        $moderate->remove($actors->fromRequest($request), $reviewId, $request->reason(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function reports(ListAdminReportsRequest $request, ActorResolver $actors, ReviewModeration $moderation): AnonymousResourceCollection
    {
        return ReportItemResource::collection(PaginatedResources::paginator($moderation->reports($actors->fromRequest($request), $request->status(), $request->pageRequest()), $request));
    }

    public function dismissReport(Request $request, int $id, ActorResolver $actors, ReviewModeration $moderation): Response
    {
        $moderation->dismiss($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
