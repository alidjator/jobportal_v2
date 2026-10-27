<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Review\ClaimReview;
use Src\Assessment\Application\Review\GetReview;
use Src\Assessment\Application\Review\GetReviewDrawing;
use Src\Assessment\Application\Review\ListReviews;
use Src\Assessment\Application\Review\MarkReportReviewed;
use Src\Assessment\Application\Review\ReleaseReview;
use Src\Assessment\Application\Review\ReviseReport;
use Src\Assessment\Application\Review\ScoreDrawing;
use Src\Assessment\Application\Review\SignReport;
use Src\Assessment\Application\Review\UpdateReview;
use Src\Assessment\Interface\Http\Requests\ListReviewsRequest;
use Src\Assessment\Interface\Http\Requests\ReviseReportRequest;
use Src\Assessment\Interface\Http\Requests\ScoreDrawingRequest;
use Src\Assessment\Interface\Http\Requests\UpdateReviewRequest;
use Src\Assessment\Interface\Http\Resources\ReviewListItemResource;
use Src\Assessment\Interface\Http\Resources\ReviewResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The psychologist's review of the reports of their provider's batteries: the queue, the draft, the drawings, the edit and the signature, and the review or revision of a report issued automatically. */
final class ReviewController extends ApiController
{
    public function index(ListReviewsRequest $request, ActorResolver $actors, ListReviews $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->status(), $request->reviewed(), $request->pageRequest(), $request->claimed());

        return ReviewListItemResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetReview $get): ReviewResource
    {
        return new ReviewResource($get->execute($actors->fromRequest($request), $id));
    }

    public function claim(Request $request, int $id, ActorResolver $actors, ClaimReview $claim): ReviewResource
    {
        return new ReviewResource($claim->execute($actors->fromRequest($request), $id));
    }

    public function release(Request $request, int $id, ActorResolver $actors, ReleaseReview $release): ReviewResource
    {
        return new ReviewResource($release->execute($actors->fromRequest($request), $id));
    }

    public function update(UpdateReviewRequest $request, int $id, ActorResolver $actors, UpdateReview $update): ReviewResource
    {
        return new ReviewResource($update->execute($actors->fromRequest($request), $id, $request->body(), $request->explanation(), $request->reviewNotes(), $request->recommendation()));
    }

    public function drawing(Request $request, int $id, int $questionId, ActorResolver $actors, GetReviewDrawing $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $id, $questionId);

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function scoreDrawing(ScoreDrawingRequest $request, int $id, int $questionId, ActorResolver $actors, ScoreDrawing $score): ReviewResource
    {
        return new ReviewResource($score->execute($actors->fromRequest($request), $id, $questionId, $request->score(), $request->note()));
    }

    /** The report that was issued automatically has been read. */
    public function markReviewed(Request $request, int $id, ActorResolver $actors, MarkReportReviewed $mark): ReviewResource
    {
        return new ReviewResource($mark->execute($actors->fromRequest($request), $id));
    }

    /** Replaces the text and the explanation of a report that was issued automatically; the number gets `-R1`, `-R2`. */
    public function revise(ReviseReportRequest $request, int $id, ActorResolver $actors, ReviseReport $revise): ReviewResource
    {
        return new ReviewResource($revise->execute($actors->fromRequest($request), $id, $request->body(), $request->explanation(), $request->ip(), $request->userAgent(), $request->recommendation()));
    }

    public function sign(Request $request, int $id, ActorResolver $actors, SignReport $sign): ReviewResource
    {
        return new ReviewResource($sign->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }
}
