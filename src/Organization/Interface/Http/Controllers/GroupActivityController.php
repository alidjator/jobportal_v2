<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Organization\Application\Activity\ListGroupActivity;
use Src\Organization\Interface\Http\Requests\ListGroupActivityRequest;
use Src\Organization\Interface\Http\Resources\GroupActivityResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;

/** The activity log of the signed-in member's group (`group.audit.view`). */
final class GroupActivityController extends ApiController
{
    public function index(ListGroupActivityRequest $request, ActorResolver $actors, ListGroupActivity $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->filters(), $request->cursorRequest());

        return GroupActivityResource::collection($page->items)->additional(CursorResources::envelope($page, $request));
    }
}
