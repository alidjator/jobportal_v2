<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Company\Application\Team\GetEmailDomains;
use Src\Company\Application\Team\ListTeamHistory;
use Src\Company\Application\Team\UpdateEmailDomains;
use Src\Company\Interface\Http\Requests\ListTeamHistoryRequest;
use Src\Company\Interface\Http\Requests\UpdateEmailDomainsRequest;
use Src\Company\Interface\Http\Resources\TeamHistoryResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;

/** The team history and the allowed email domains of the signed-in company admin's own company. */
final class TeamSettingsController extends ApiController
{
    public function history(ListTeamHistoryRequest $request, ActorResolver $actors, ListTeamHistory $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->action(), $request->userId(), $request->from(), $request->to(), $request->cursorRequest());

        return TeamHistoryResource::collection($page->items)->additional(CursorResources::envelope($page, $request));
    }

    public function emailDomains(Request $request, ActorResolver $actors, GetEmailDomains $get): JsonResponse
    {
        return response()->json(['data' => ['domains' => $get->execute($actors->fromRequest($request))]]);
    }

    public function updateEmailDomains(UpdateEmailDomainsRequest $request, ActorResolver $actors, UpdateEmailDomains $update): JsonResponse
    {
        return response()->json(['data' => ['domains' => $update->execute($actors->fromRequest($request), $request->domains(), $request->ip(), $request->userAgent())]]);
    }
}
