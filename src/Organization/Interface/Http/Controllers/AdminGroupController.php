<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Organization\Application\Admin\ChangeGroupStatus;
use Src\Organization\Application\Admin\CreateGroup;
use Src\Organization\Application\Admin\GetGroup;
use Src\Organization\Application\Admin\LinkTenant;
use Src\Organization\Application\Admin\ListGroups;
use Src\Organization\Application\Admin\UnlinkTenant;
use Src\Organization\Interface\Http\Requests\CreateGroupRequest;
use Src\Organization\Interface\Http\Requests\LinkTenantRequest;
use Src\Organization\Interface\Http\Requests\SuspendGroupRequest;
use Src\Organization\Interface\Http\Resources\GroupResource;
use Src\Organization\Interface\Http\Resources\GroupSummaryResource;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The customer groups of the ERP, for platform admins holding `companies.manage`: creating them, suspending them and linking tenants to their legal entities. */
final class AdminGroupController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListGroups $list): AnonymousResourceCollection
    {
        $page = new PageRequest($request->integer('page', 1), $request->integer('per_page', PageRequest::DEFAULT_PER_PAGE));

        return GroupSummaryResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $page), $request));
    }

    public function store(CreateGroupRequest $request, ActorResolver $actors, CreateGroup $create): JsonResponse
    {
        $group = $create->execute($actors->fromRequest($request), $request->groupName(), $request->firstLegalEntity(), $request->ip(), $request->userAgent());

        return (new GroupResource($group))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetGroup $get): GroupResource
    {
        return new GroupResource($get->execute($actors->fromRequest($request), $id));
    }

    public function suspend(SuspendGroupRequest $request, int $id, ActorResolver $actors, ChangeGroupStatus $status, GetGroup $get): GroupResource
    {
        $actor = $actors->fromRequest($request);
        $status->suspend($actor, $id, $request->reason(), $request->ip(), $request->userAgent());

        return new GroupResource($get->execute($actor, $id));
    }

    public function restore(Request $request, int $id, ActorResolver $actors, ChangeGroupStatus $status, GetGroup $get): GroupResource
    {
        $actor = $actors->fromRequest($request);
        $status->restore($actor, $id, $request->ip(), $request->userAgent());

        return new GroupResource($get->execute($actor, $id));
    }

    public function linkTenant(LinkTenantRequest $request, int $id, int $entityId, ActorResolver $actors, LinkTenant $link, GetGroup $get): GroupResource
    {
        $actor = $actors->fromRequest($request);
        $link->execute($actor, $id, $entityId, $request->companyId(), $request->ip(), $request->userAgent());

        return new GroupResource($get->execute($actor, $id));
    }

    public function unlinkTenant(Request $request, int $id, int $entityId, ActorResolver $actors, UnlinkTenant $unlink): Response
    {
        $unlink->execute($actors->fromRequest($request), $id, $entityId, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
