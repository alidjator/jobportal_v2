<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Recruitment\Application\GetJobScreeningQuestions;
use Src\Recruitment\Application\GetPublicScreeningQuestions;
use Src\Recruitment\Application\ReplaceJobScreeningQuestions;
use Src\Recruitment\Interface\Http\Requests\ReplaceJobScreeningQuestionsRequest;
use Src\Recruitment\Interface\Http\Resources\JobQuestionResource;
use Src\Recruitment\Interface\Http\Resources\PublicScreeningQuestionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/**
 * The screening questions of a job. Company members who may see the job read and replace them; the public reads only what an applicant needs. `{job}` on the
 * public route is the id, optionally followed by the cosmetic slug, as on the job page.
 */
final class JobScreeningQuestionController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, GetJobScreeningQuestions $get): AnonymousResourceCollection
    {
        return JobQuestionResource::collection($get->execute($actors->fromRequest($request), $id));
    }

    public function replace(ReplaceJobScreeningQuestionsRequest $request, int $id, ActorResolver $actors, ReplaceJobScreeningQuestions $replace): AnonymousResourceCollection
    {
        return JobQuestionResource::collection($replace->execute($actors->fromRequest($request), $id, $request->items()));
    }

    public function publicIndex(string $job, GetPublicScreeningQuestions $get): AnonymousResourceCollection
    {
        return PublicScreeningQuestionResource::collection($get->execute((int) $job));
    }
}
