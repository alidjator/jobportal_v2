<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Recruitment\Application\Checks\AnswerCheckConsent;
use Src\Recruitment\Interface\Http\Requests\ConsentCheckRequest;
use Src\Recruitment\Interface\Http\Requests\RefuseCheckRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The candidate answers the consent a pre-employment check asks for. The checks themselves are part of `GET me/applications/{id}`. */
final class MyCheckController extends ApiController
{
    public function consent(ConsentCheckRequest $request, int $id, int $checkId, ActorResolver $actors, AnswerCheckConsent $answer): Response
    {
        $answer->consent($actors->fromRequest($request), $id, $checkId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function refuse(RefuseCheckRequest $request, int $id, int $checkId, ActorResolver $actors, AnswerCheckConsent $answer): Response
    {
        $answer->refuse($actors->fromRequest($request), $id, $checkId, $request->reason());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
