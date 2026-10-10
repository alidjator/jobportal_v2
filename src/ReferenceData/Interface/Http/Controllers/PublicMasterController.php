<?php

namespace Src\ReferenceData\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\ReferenceData\Application\Masters\ListMasterItems;
use Src\ReferenceData\Application\Masters\MasterItem;
use Src\ReferenceData\Interface\Http\Requests\ListMasterItemsRequest;
use Src\ReferenceData\Interface\Http\Resources\MasterItemResource;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Public reading of every master: active items only. Paginated masters return `links` and `meta`; the others return the whole list. */
final class PublicMasterController extends ApiController
{
    public function index(ListMasterItemsRequest $request, ListMasterItems $list): AnonymousResourceCollection
    {
        return $this->respond($request, $list->execute($request->definition(), $request->toQuery(onlyActive: true)));
    }

    /** @param  Page<MasterItem>|list<MasterItem>  $result */
    private function respond(Request $request, Page|array $result): AnonymousResourceCollection
    {
        return $result instanceof Page
            ? MasterItemResource::collection(PaginatedResources::paginator($result, $request))
            : MasterItemResource::collection($result);
    }
}
