<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\ChangeScreeningQuestionArchive;
use Src\Recruitment\Application\CreateScreeningQuestion;
use Src\Recruitment\Application\DeleteScreeningQuestion;
use Src\Recruitment\Application\GetScreeningQuestion;
use Src\Recruitment\Application\ListScreeningQuestions;
use Src\Recruitment\Application\UpdateScreeningQuestion;
use Src\Recruitment\Interface\Http\Requests\CreateScreeningQuestionRequest;
use Src\Recruitment\Interface\Http\Requests\ListScreeningQuestionsRequest;
use Src\Recruitment\Interface\Http\Requests\UpdateScreeningQuestionRequest;
use Src\Recruitment\Interface\Http\Resources\ScreeningQuestionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The screening question library of the caller's own company. Every member reads it; only company admins change it. */
final class ScreeningQuestionController extends ApiController
{
    public function index(ListScreeningQuestionsRequest $request, ActorResolver $actors, ListScreeningQuestions $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->archived(), $request->search(), $request->pageRequest());

        return ScreeningQuestionResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetScreeningQuestion $get): ScreeningQuestionResource
    {
        return new ScreeningQuestionResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(CreateScreeningQuestionRequest $request, ActorResolver $actors, CreateScreeningQuestion $create): JsonResponse
    {
        $question = $create->execute($actors->fromRequest($request), $request->type(), $request->text(), $request->labels());

        return (new ScreeningQuestionResource($question))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateScreeningQuestionRequest $request, int $id, ActorResolver $actors, UpdateScreeningQuestion $update): ScreeningQuestionResource
    {
        return new ScreeningQuestionResource($update->execute($actors->fromRequest($request), $id, $request->text(), $request->options()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteScreeningQuestion $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function archive(Request $request, int $id, ActorResolver $actors, ChangeScreeningQuestionArchive $change): ScreeningQuestionResource
    {
        return new ScreeningQuestionResource($change->execute($actors->fromRequest($request), $id, true));
    }

    public function restore(Request $request, int $id, ActorResolver $actors, ChangeScreeningQuestionArchive $change): ScreeningQuestionResource
    {
        return new ScreeningQuestionResource($change->execute($actors->fromRequest($request), $id, false));
    }
}
