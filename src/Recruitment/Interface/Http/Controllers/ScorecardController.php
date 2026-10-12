<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Recruitment\Application\GetMyScorecard;
use Src\Recruitment\Application\ListScorecardRevisions;
use Src\Recruitment\Application\ListSentScorecards;
use Src\Recruitment\Application\SaveScorecard;
use Src\Recruitment\Application\SubmitScorecard;
use Src\Recruitment\Interface\Http\Requests\SaveScorecardRequest;
use Src\Recruitment\Interface\Http\Resources\ScorecardResource;
use Src\Recruitment\Interface\Http\Resources\ScorecardRevisionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The scorecards of an interview: the caller's own (panel members) and the sent ones (everyone who may see them). */
final class ScorecardController extends ApiController
{
    public function show(Request $request, int $id, ActorResolver $actors, GetMyScorecard $get): ScorecardResource
    {
        return new ScorecardResource($get->execute($actors->fromRequest($request), $id));
    }

    public function update(SaveScorecardRequest $request, int $id, ActorResolver $actors, SaveScorecard $save): ScorecardResource
    {
        return new ScorecardResource($save->execute($actors->fromRequest($request), $id, $request->recommendation(), $request->note(), $request->answers()));
    }

    public function submit(Request $request, int $id, ActorResolver $actors, SubmitScorecard $submit): ScorecardResource
    {
        return new ScorecardResource($submit->execute($actors->fromRequest($request), $id));
    }

    public function index(Request $request, int $id, ActorResolver $actors, ListSentScorecards $list): AnonymousResourceCollection
    {
        return ScorecardResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function revisions(Request $request, int $id, int $scorecardId, ActorResolver $actors, ListScorecardRevisions $list): AnonymousResourceCollection
    {
        return ScorecardRevisionResource::collection($list->execute($actors->fromRequest($request), $id, $scorecardId));
    }
}
