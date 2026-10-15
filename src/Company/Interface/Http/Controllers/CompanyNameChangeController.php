<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Company\Application\NameChange\CancelNameChangeRequest;
use Src\Company\Application\NameChange\ListMyNameChangeRequests;
use Src\Company\Application\NameChange\RequestNameChange;
use Src\Company\Interface\Http\Requests\RequestNameChangeRequest;
use Src\Company\Interface\Http\Resources\NameChangeRequestResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The name change requests of the caller's own (verified) company, for members who may change the company profile. */
final class CompanyNameChangeController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListMyNameChangeRequests $list): AnonymousResourceCollection
    {
        return NameChangeRequestResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(RequestNameChangeRequest $request, ActorResolver $actors, RequestNameChange $requestChange): JsonResponse
    {
        $created = $requestChange->execute(
            $actors->fromRequest($request), $request->newName(), $request->reason(), $request->contents(), $request->originalName(), $request->ip(), $request->userAgent(),
        );

        return (new NameChangeRequestResource($created))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function cancel(Request $request, int $id, ActorResolver $actors, CancelNameChangeRequest $cancel): NameChangeRequestResource
    {
        return new NameChangeRequestResource($cancel->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }
}
