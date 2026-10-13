<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Company\Application\Team\ChangeMemberRole;
use Src\Company\Application\Team\ListMemberJobs;
use Src\Company\Application\Team\ListMembers;
use Src\Company\Application\Team\RemoveMember;
use Src\Company\Interface\Http\Requests\ChangeMemberRoleRequest;
use Src\Company\Interface\Http\Resources\MemberJobResource;
use Src\Company\Interface\Http\Resources\MemberResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The members of the signed-in company admin's own company. */
final class TeamMemberController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListMembers $list): AnonymousResourceCollection
    {
        return MemberResource::collection($list->execute($actors->fromRequest($request)));
    }

    /** The jobs the member is assigned to, whatever their status, newest first. */
    public function jobs(Request $request, int $userId, ActorResolver $actors, ListMemberJobs $list): AnonymousResourceCollection
    {
        return MemberJobResource::collection($list->execute($actors->fromRequest($request), $userId));
    }

    public function destroy(Request $request, int $userId, ActorResolver $actors, RemoveMember $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $userId, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function changeRole(ChangeMemberRoleRequest $request, int $userId, ActorResolver $actors, ChangeMemberRole $change): Response
    {
        $change->execute($actors->fromRequest($request), $userId, $request->role(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
