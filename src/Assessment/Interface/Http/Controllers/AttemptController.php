<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Assessment\Application\Attempts\AttemptView;
use Src\Assessment\Application\Attempts\FinishSection;
use Src\Assessment\Application\Attempts\GetAttempt;
use Src\Assessment\Application\Attempts\MoveToQuestion;
use Src\Assessment\Application\Attempts\ReportIntegrityEvent;
use Src\Assessment\Application\Attempts\SaveAnswer;
use Src\Assessment\Application\Attempts\SaveColumn;
use Src\Assessment\Application\Attempts\StartAttempt;
use Src\Assessment\Application\Attempts\SubmitAttempt;
use Src\Assessment\Application\Attempts\UploadDrawing;
use Src\Assessment\Interface\Http\Requests\IntegrityEventRequest;
use Src\Assessment\Interface\Http\Requests\MoveQuestionRequest;
use Src\Assessment\Interface\Http\Requests\SaveAnswerRequest;
use Src\Assessment\Interface\Http\Requests\SaveColumnRequest;
use Src\Assessment\Interface\Http\Requests\StartAttemptRequest;
use Src\Assessment\Interface\Http\Requests\UploadDrawingRequest;
use Src\Assessment\Interface\Http\Resources\AttemptResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The candidate taking a test: start, read, answer, move, hand in. Every call is checked against the server's clock. */
final class AttemptController extends ApiController
{
    /** Starts the test: 201 when it begins now, 200 when it was already started. */
    public function start(StartAttemptRequest $request, int $id, ActorResolver $actors, StartAttempt $start): JsonResponse
    {
        $result = $start->execute($actors->fromRequest($request), $id, $request->consent(), $request->ip(), $request->videoConsent());

        return (new AttemptResource($result['view']))->response()->setStatusCode($result['created'] ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_OK);
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetAttempt $get): AttemptResource
    {
        return new AttemptResource($get->execute($actors->fromRequest($request), $id, $request->ip()));
    }

    public function saveAnswer(SaveAnswerRequest $request, int $id, int $questionId, ActorResolver $actors, SaveAnswer $save): Response
    {
        $save->save($actors->fromRequest($request), $id, $questionId, $request->optionPositions(), $request->number(), $request->text(), $request->ip());

        return response()->noContent();
    }

    public function clearAnswer(Request $request, int $id, int $questionId, ActorResolver $actors, SaveAnswer $save): Response
    {
        $save->clear($actors->fromRequest($request), $id, $questionId, $request->ip());

        return response()->noContent();
    }

    public function move(MoveQuestionRequest $request, int $id, ActorResolver $actors, MoveToQuestion $move): AttemptResource
    {
        return $this->view($move->execute($actors->fromRequest($request), $id, $request->index(), $request->ip()));
    }

    public function finishSection(Request $request, int $id, ActorResolver $actors, FinishSection $finish): AttemptResource
    {
        return $this->view($finish->execute($actors->fromRequest($request), $id, $request->ip()));
    }

    public function saveColumn(SaveColumnRequest $request, int $id, int $column, ActorResolver $actors, SaveColumn $save): Response
    {
        $save->execute($actors->fromRequest($request), $id, $column, $request->sums(), $request->ip());

        return response()->noContent();
    }

    public function uploadDrawing(UploadDrawingRequest $request, int $id, int $questionId, ActorResolver $actors, UploadDrawing $upload): Response
    {
        $upload->execute($actors->fromRequest($request), $id, $questionId, $request->source(), $request->contents(), $request->ip());

        return response()->noContent();
    }

    public function reportEvent(IntegrityEventRequest $request, int $id, ActorResolver $actors, ReportIntegrityEvent $report): Response
    {
        $report->execute($actors->fromRequest($request), $id, $request->type(), $request->ip());

        return response()->noContent();
    }

    public function submit(Request $request, int $id, ActorResolver $actors, SubmitAttempt $submit): AttemptResource
    {
        return $this->view($submit->execute($actors->fromRequest($request), $id, $request->ip()));
    }

    private function view(AttemptView $view): AttemptResource
    {
        return new AttemptResource($view);
    }
}
