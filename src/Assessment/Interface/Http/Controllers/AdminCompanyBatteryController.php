<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Admin\GrantBattery;
use Src\Assessment\Application\Admin\ListCompanyBatteries;
use Src\Assessment\Application\Admin\ListPublishedBatteries;
use Src\Assessment\Application\Admin\RevokeBattery;
use Src\Assessment\Interface\Http\Requests\GrantBatteryRequest;
use Src\Assessment\Interface\Http\Requests\ListCatalogRequest;
use Src\Assessment\Interface\Http\Resources\CatalogTestResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The batteries an admin holding `assessment_providers.manage` lets a company use. */
final class AdminCompanyBatteryController extends ApiController
{
    public function published(ListCatalogRequest $request, ActorResolver $actors, ListPublishedBatteries $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->search(), $request->pageRequest());

        return CatalogTestResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function index(Request $request, int $id, ActorResolver $actors, ListCompanyBatteries $list): AnonymousResourceCollection
    {
        return CatalogTestResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function store(GrantBatteryRequest $request, int $id, ActorResolver $actors, GrantBattery $grant): Response
    {
        $grant->execute($actors->fromRequest($request), $id, $request->testId());

        return response()->noContent();
    }

    public function destroy(Request $request, int $id, int $testId, ActorResolver $actors, RevokeBattery $revoke): Response
    {
        $revoke->execute($actors->fromRequest($request), $id, $testId);

        return response()->noContent();
    }
}
