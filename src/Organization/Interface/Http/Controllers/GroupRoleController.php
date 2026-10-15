<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Organization\Application\Roles\ListGroupMembers;
use Src\Organization\Application\Roles\RemoveGroupRole;
use Src\Organization\Application\Roles\SetGroupRole;
use Src\Organization\Interface\Http\Requests\SetGroupRoleRequest;
use Src\Organization\Interface\Http\Resources\GroupMemberResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/**
 * The group roles: a group member with `group.members.manage` manages those of their own group (`organization/members`), a platform admin those of any group
 * (`admin/groups/{id}/members`), the first group admin included. `$groupId` is null on the group side: the group of the caller is the one.
 */
final class GroupRoleController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListGroupMembers $list): AnonymousResourceCollection
    {
        return GroupMemberResource::collection($list->execute($actors->fromRequest($request), $this->groupId($request)));
    }

    public function set(SetGroupRoleRequest $request, ActorResolver $actors, SetGroupRole $set): GroupMemberResource
    {
        return new GroupMemberResource($set->execute($actors->fromRequest($request), $this->groupId($request), $this->userId($request), $request->permissions(), $request->ip(), $request->userAgent()));
    }

    public function remove(Request $request, ActorResolver $actors, RemoveGroupRole $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $this->groupId($request), $this->userId($request), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    /** The group named in the path (platform admin), or null on the group side, where the group of the caller is the one. Read by name: the two routes have different parameters. */
    private function groupId(Request $request): ?int
    {
        $id = $request->route('id');

        return $id === null ? null : (int) $id;
    }

    private function userId(Request $request): int
    {
        return (int) $request->route('userId');
    }
}
