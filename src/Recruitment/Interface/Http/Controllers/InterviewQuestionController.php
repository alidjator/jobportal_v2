<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Recruitment\Application\GetInterviewQuestions;
use Src\Recruitment\Application\ReplaceInterviewQuestions;
use Src\Recruitment\Interface\Http\Requests\ReplaceInterviewQuestionsRequest;
use Src\Recruitment\Interface\Http\Resources\InterviewQuestionsResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The questions an interview keeps (a copy of its kit), for the members who may see its application. */
final class InterviewQuestionController extends ApiController
{
    public function show(Request $request, int $id, ActorResolver $actors, GetInterviewQuestions $get): InterviewQuestionsResource
    {
        return new InterviewQuestionsResource($get->execute($actors->fromRequest($request), $id));
    }

    public function update(ReplaceInterviewQuestionsRequest $request, int $id, ActorResolver $actors, ReplaceInterviewQuestions $replace): InterviewQuestionsResource
    {
        return new InterviewQuestionsResource($replace->execute($actors->fromRequest($request), $id, $request->questions()));
    }
}
