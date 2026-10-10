<?php

namespace Src\Newsletter\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Newsletter\Application\DeleteSubscriber;
use Src\Newsletter\Application\ListSubscribers;
use Src\Newsletter\Interface\Http\Requests\ListSubscribersRequest;
use Src\Newsletter\Interface\Http\Resources\SubscriberResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The email list, for admins with `newsletter.manage`. The answer also carries `counts`, the number of subscribers per status of the whole list. */
final class SubscriberController extends ApiController
{
    public function index(ListSubscribersRequest $request, ActorResolver $actors, ListSubscribers $list): AnonymousResourceCollection
    {
        $result = $list->execute($actors->fromRequest($request), $request->search(), $request->status(), $request->pageRequest());

        return SubscriberResource::collection(PaginatedResources::paginator($result->page, $request))->additional(['counts' => $result->counts]);
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteSubscriber $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
