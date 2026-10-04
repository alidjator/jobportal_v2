<?php

namespace Src\ReferenceData\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\ReferenceData\Application\Locations\ListLocations;
use Src\ReferenceData\Interface\Http\Requests\ListLocationsRequest;
use Src\ReferenceData\Interface\Http\Resources\LocationResource;
use Src\Shared\Interface\Http\ApiController;

/** Public reading of the administrative hierarchy. Every level but the provinces is listed per parent (`filter[province_id]`, ...), so lists are small and not paginated. */
final class PublicLocationController extends ApiController
{
    public function index(ListLocationsRequest $request, ListLocations $list): AnonymousResourceCollection
    {
        return LocationResource::collection($list->execute($request->level(), $request->parentId()));
    }
}
