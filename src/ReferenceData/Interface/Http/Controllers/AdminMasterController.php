<?php

namespace Src\ReferenceData\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\ReferenceData\Application\Masters\CreateMasterItem;
use Src\ReferenceData\Application\Masters\ListMasterItems;
use Src\ReferenceData\Application\Masters\MasterItem;
use Src\ReferenceData\Application\Masters\UpdateMasterItem;
use Src\ReferenceData\Interface\Http\Requests\ListMasterItemsRequest;
use Src\ReferenceData\Interface\Http\Requests\MasterItemRequest;
use Src\ReferenceData\Interface\Http\Resources\AdminMasterItemResource;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Admin management of the masters (permission `reference_data.manage`): list including inactive items, create, update. There is no delete: retire an item with `is_active`. */
final class AdminMasterController extends ApiController
{
    public function index(ListMasterItemsRequest $request, ListMasterItems $list): AnonymousResourceCollection
    {
        return $this->respond($request, $list->execute($request->definition(), $request->toQuery(onlyActive: false)));
    }

    public function store(MasterItemRequest $request, CreateMasterItem $create): JsonResponse
    {
        $item = $create->execute($request->definition(), $request->name(), $request->sortOrder(), $request->isActive(), $request->extras());

        return (new AdminMasterItemResource($item))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(int $id, MasterItemRequest $request, UpdateMasterItem $update): AdminMasterItemResource
    {
        return new AdminMasterItemResource($update->execute($request->definition(), $id, $request->name(), $request->sortOrder(), $request->isActive(), $request->extras()));
    }

    /** @param  Page<MasterItem>|list<MasterItem>  $result */
    private function respond(Request $request, Page|array $result): AnonymousResourceCollection
    {
        return $result instanceof Page
            ? AdminMasterItemResource::collection(PaginatedResources::paginator($result, $request))
            : AdminMasterItemResource::collection($result);
    }
}
