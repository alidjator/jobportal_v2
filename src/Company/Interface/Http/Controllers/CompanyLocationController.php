<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Company\Application\Locations\ActivateCompanyLocation;
use Src\Company\Application\Locations\CreateCompanyLocation;
use Src\Company\Application\Locations\DeactivateCompanyLocation;
use Src\Company\Application\Locations\DeleteCompanyLocation;
use Src\Company\Application\Locations\ListCompanyLocations;
use Src\Company\Application\Locations\SetMainCompanyLocation;
use Src\Company\Application\Locations\UpdateCompanyLocation;
use Src\Company\Interface\Http\Requests\CreateCompanyLocationRequest;
use Src\Company\Interface\Http\Requests\ListCompanyLocationsRequest;
use Src\Company\Interface\Http\Requests\UpdateCompanyLocationRequest;
use Src\Company\Interface\Http\Resources\CompanyLocationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The work locations of the signed-in member's own company: any member reads them, a member who may change the company profile manages them. */
final class CompanyLocationController extends ApiController
{
    public function index(ListCompanyLocationsRequest $request, ActorResolver $actors, ListCompanyLocations $list): AnonymousResourceCollection
    {
        return CompanyLocationResource::collection($list->execute($actors->fromRequest($request), $request->status()));
    }

    public function store(CreateCompanyLocationRequest $request, ActorResolver $actors, CreateCompanyLocation $create): JsonResponse
    {
        $view = $create->execute($actors->fromRequest($request), $request->location(), $request->ip(), $request->userAgent());

        return (new CompanyLocationResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCompanyLocationRequest $request, ActorResolver $actors, UpdateCompanyLocation $update, int $id): CompanyLocationResource
    {
        return new CompanyLocationResource($update->execute($actors->fromRequest($request), $id, $request->changes(), $request->ip(), $request->userAgent()));
    }

    public function makeMain(Request $request, ActorResolver $actors, SetMainCompanyLocation $setMain, int $id): CompanyLocationResource
    {
        return new CompanyLocationResource($setMain->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    public function deactivate(Request $request, ActorResolver $actors, DeactivateCompanyLocation $deactivate, int $id): CompanyLocationResource
    {
        return new CompanyLocationResource($deactivate->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    public function activate(Request $request, ActorResolver $actors, ActivateCompanyLocation $activate, int $id): CompanyLocationResource
    {
        return new CompanyLocationResource($activate->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    public function destroy(Request $request, ActorResolver $actors, DeleteCompanyLocation $delete, int $id): Response
    {
        $delete->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
