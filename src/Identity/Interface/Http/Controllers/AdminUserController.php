<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Identity\Application\Users\GetAdminUser;
use Src\Identity\Application\Users\ListAdminUsers;
use Src\Identity\Interface\Http\Requests\ListAdminUsersRequest;
use Src\Identity\Interface\Http\Resources\AdminUserResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Read-only view of accounts for admins holding `users.view`. */
final class AdminUserController extends ApiController
{
    public function index(ListAdminUsersRequest $request, ListAdminUsers $list): AnonymousResourceCollection
    {
        return AdminUserResource::collection(PaginatedResources::paginator($list->execute($request->search(), $request->pageRequest()), $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetAdminUser $get): AdminUserResource
    {
        return new AdminUserResource($get->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }
}
