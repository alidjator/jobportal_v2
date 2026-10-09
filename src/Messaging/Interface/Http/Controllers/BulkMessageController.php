<?php

namespace Src\Messaging\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Messaging\Application\BulkSendMessage;
use Src\Messaging\Interface\Http\Requests\BulkMessageRequest;
use Src\Messaging\Interface\Http\Resources\BulkMessageResultResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** One message to the applicants of several applications of one job; the answer says what happened to each, in the order of the selection. */
final class BulkMessageController extends ApiController
{
    public function store(BulkMessageRequest $request, int $id, ActorResolver $actors, BulkSendMessage $bulk): AnonymousResourceCollection
    {
        return BulkMessageResultResource::collection($bulk->execute($actors->fromRequest($request), $id, $request->applicationIds(), $request->toInput()));
    }
}
