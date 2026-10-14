<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Company\Application\Team\GetMember;
use Src\Company\Application\Team\ListMemberActivity;
use Src\Company\Application\Team\ListMemberJobs;
use Src\Company\Application\Team\ListMembers;
use Src\Company\Application\Team\MakeOwner;
use Src\Company\Application\Team\ReactivateMember;
use Src\Company\Application\Team\RemoveMember;
use Src\Company\Application\Team\SuspendMember;
use Src\Company\Application\Team\UpdateMember;
use Src\Company\Interface\Http\Requests\ListMemberActivityRequest;
use Src\Company\Interface\Http\Requests\ListTeamRequest;
use Src\Company\Interface\Http\Requests\MakeOwnerRequest;
use Src\Company\Interface\Http\Requests\RemoveMemberRequest;
use Src\Company\Interface\Http\Requests\SuspendMemberRequest;
use Src\Company\Interface\Http\Requests\UpdateMemberRequest;
use Src\Company\Interface\Http\Resources\MemberActivityResource;
use Src\Company\Interface\Http\Resources\MemberDetailResource;
use Src\Company\Interface\Http\Resources\MemberJobResource;
use Src\Company\Interface\Http\Resources\TeamRowResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;
use Src\Shared\Interface\Http\PaginatedResources;

/** The members of the signed-in company admin's own company. */
final class TeamMemberController extends ApiController
{
    /** The team in one list: members and open invitations, searchable and paginated. `meta.member_count` counts the members, search aside. */
    public function index(ListTeamRequest $request, ActorResolver $actors, ListMembers $list): AnonymousResourceCollection
    {
        $team = $list->execute($actors->fromRequest($request), $request->search(), $request->pageRequest());

        return TeamRowResource::collection(PaginatedResources::paginator($team->page, $request))->additional(['meta' => ['member_count' => $team->memberCount]]);
    }

    public function show(Request $request, int $userId, ActorResolver $actors, GetMember $get): MemberDetailResource
    {
        return new MemberDetailResource($get->execute($actors->fromRequest($request), $userId));
    }

    /** The jobs the member is assigned to, whatever their status, newest first. */
    public function jobs(Request $request, int $userId, ActorResolver $actors, ListMemberJobs $list): AnonymousResourceCollection
    {
        return MemberJobResource::collection($list->execute($actors->fromRequest($request), $userId));
    }

    public function activity(ListMemberActivityRequest $request, int $userId, ActorResolver $actors, ListMemberActivity $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $userId, $request->action(), $request->from(), $request->to(), $request->cursorRequest());

        return MemberActivityResource::collection($page->items)->additional(CursorResources::envelope($page, $request));
    }

    public function destroy(RemoveMemberRequest $request, int $userId, ActorResolver $actors, RemoveMember $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $userId, $request->transferToUserId(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function update(UpdateMemberRequest $request, int $userId, ActorResolver $actors, UpdateMember $update): Response
    {
        $update->execute($actors->fromRequest($request), $userId, $request->name(), $request->role(), $request->permissions(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function suspend(SuspendMemberRequest $request, int $userId, ActorResolver $actors, SuspendMember $suspend): Response
    {
        $suspend->execute($actors->fromRequest($request), $userId, $request->message(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function reactivate(Request $request, int $userId, ActorResolver $actors, ReactivateMember $reactivate): Response
    {
        $reactivate->execute($actors->fromRequest($request), $userId, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function makeOwner(MakeOwnerRequest $request, int $userId, ActorResolver $actors, MakeOwner $makeOwner): Response
    {
        $makeOwner->execute($actors->fromRequest($request), $userId, $request->password(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
