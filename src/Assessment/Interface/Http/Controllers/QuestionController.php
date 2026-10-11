<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Authoring\AddQuestion;
use Src\Assessment\Application\Authoring\DeleteQuestion;
use Src\Assessment\Application\Authoring\ListQuestions;
use Src\Assessment\Application\Authoring\ReorderQuestions;
use Src\Assessment\Application\Authoring\UpdateQuestion;
use Src\Assessment\Interface\Http\Requests\OrderRequest;
use Src\Assessment\Interface\Http\Requests\QuestionRequest;
use Src\Assessment\Interface\Http\Resources\QuestionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The questions of a section of the author's test (see `TestController`); the whole section comes in one response, at most the section limit. */
final class QuestionController extends ApiController
{
    public function index(Request $request, int $sectionId, ActorResolver $actors, ListQuestions $list): AnonymousResourceCollection
    {
        return QuestionResource::collection($list->execute($actors->fromRequest($request), $sectionId));
    }

    public function store(QuestionRequest $request, int $sectionId, ActorResolver $actors, AddQuestion $add): JsonResponse
    {
        $question = $add->execute($actors->fromRequest($request), $sectionId, $request->draft());

        return (new QuestionResource($question))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(QuestionRequest $request, int $questionId, ActorResolver $actors, UpdateQuestion $update): QuestionResource
    {
        return new QuestionResource($update->execute($actors->fromRequest($request), $questionId, $request->draft()));
    }

    public function destroy(Request $request, int $questionId, ActorResolver $actors, DeleteQuestion $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $questionId);

        return response()->noContent();
    }

    public function reorder(OrderRequest $request, int $sectionId, ActorResolver $actors, ReorderQuestions $reorder): AnonymousResourceCollection
    {
        return QuestionResource::collection($reorder->execute($actors->fromRequest($request), $sectionId, $request->ids()));
    }
}
