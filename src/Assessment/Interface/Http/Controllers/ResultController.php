<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Results\DownloadReport;
use Src\Assessment\Application\Results\GetInvitationResult;
use Src\Assessment\Application\Results\ListIntegrityEvents;
use Src\Assessment\Application\Results\ListScoringAnswers;
use Src\Assessment\Application\Results\OpenCandidateVideo;
use Src\Assessment\Application\Results\ScoreAnswer;
use Src\Assessment\Interface\Http\Requests\ScoreAnswerRequest;
use Src\Assessment\Interface\Http\Resources\IntegrityLogResource;
use Src\Assessment\Interface\Http\Resources\InvitationResultResource;
use Src\Assessment\Interface\Http\Resources\ScoringItemResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\FileStreamResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** The results of the tests sent for an application: scores, the integrity log, the assessors' scoring and the signed report as a PDF. */
final class ResultController extends ApiController
{
    public function show(Request $request, int $id, ActorResolver $actors, GetInvitationResult $get): InvitationResultResource
    {
        return new InvitationResultResource($get->execute($actors->fromRequest($request), $id));
    }

    public function integrityEvents(Request $request, int $id, ActorResolver $actors, ListIntegrityEvents $list): IntegrityLogResource
    {
        return new IntegrityLogResource($list->execute($actors->fromRequest($request), $id));
    }

    public function scoring(Request $request, int $id, ActorResolver $actors, ListScoringAnswers $list): AnonymousResourceCollection
    {
        return ScoringItemResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function score(ScoreAnswerRequest $request, int $id, int $questionId, ActorResolver $actors, ScoreAnswer $score): ScoringItemResource
    {
        return new ScoringItemResource($score->execute($actors->fromRequest($request), $id, $questionId, $request->score()));
    }

    public function video(Request $request, int $id, int $questionId, ActorResolver $actors, OpenCandidateVideo $open): SymfonyResponse
    {
        return FileStreamResponse::make($request, $open->execute($actors->fromRequest($request), $id, $questionId));
    }

    public function report(Request $request, int $id, ActorResolver $actors, DownloadReport $download): Response
    {
        $file = $download->execute($actors->fromRequest($request), $id);

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->name, $file->name),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
