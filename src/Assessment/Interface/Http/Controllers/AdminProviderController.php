<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Admin\GetProvider;
use Src\Assessment\Application\Admin\ListProviders;
use Src\Assessment\Application\Admin\RegisterProvider;
use Src\Assessment\Application\Admin\RestoreProvider;
use Src\Assessment\Application\Admin\SuspendProvider;
use Src\Assessment\Application\Admin\UpdateProvider;
use Src\Assessment\Interface\Http\Requests\ListProvidersRequest;
use Src\Assessment\Interface\Http\Requests\ProviderRequest;
use Src\Assessment\Interface\Http\Resources\ProviderResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Partner providers for platform admins holding `assessment_providers.manage`. */
final class AdminProviderController extends ApiController
{
    public function index(ListProvidersRequest $request, ActorResolver $actors, ListProviders $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->search(), $request->status(), $request->pageRequest());

        return ProviderResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetProvider $get): ProviderResource
    {
        return new ProviderResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(ProviderRequest $request, ActorResolver $actors, RegisterProvider $register): JsonResponse
    {
        $provider = $register->execute($actors->fromRequest($request), $request->name(), $request->legalName(), $request->ip(), $request->userAgent());

        return (new ProviderResource($provider))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(ProviderRequest $request, int $id, ActorResolver $actors, UpdateProvider $update): ProviderResource
    {
        return new ProviderResource($update->execute($actors->fromRequest($request), $id, $request->name(), $request->legalName()));
    }

    public function suspend(Request $request, int $id, ActorResolver $actors, SuspendProvider $suspend): Response
    {
        $suspend->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function restore(Request $request, int $id, ActorResolver $actors, RestoreProvider $restore): Response
    {
        $restore->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
