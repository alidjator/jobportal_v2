<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Platform\Application\EmailSuppression\AddEmailSuppression;
use Src\Platform\Application\EmailSuppression\ListEmailSuppressions;
use Src\Platform\Application\EmailSuppression\RemoveEmailSuppression;
use Src\Platform\Interface\Http\Requests\AddEmailSuppressionRequest;
use Src\Platform\Interface\Http\Requests\ListEmailSuppressionsRequest;
use Src\Platform\Interface\Http\Resources\EmailSuppressionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The email suppression list for admins holding `settings.manage`. */
final class AdminEmailSuppressionController extends ApiController
{
    public function index(ListEmailSuppressionsRequest $request, ListEmailSuppressions $list): AnonymousResourceCollection
    {
        return EmailSuppressionResource::collection(PaginatedResources::paginator($list->execute($request->search(), $request->pageRequest()), $request));
    }

    public function store(AddEmailSuppressionRequest $request, ActorResolver $actors, AddEmailSuppression $add): JsonResponse
    {
        $entry = $add->execute($actors->fromRequest($request), $request->email(), $request->details(), $request->ip(), $request->userAgent());

        return (new EmailSuppressionResource($entry))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, RemoveEmailSuppression $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
