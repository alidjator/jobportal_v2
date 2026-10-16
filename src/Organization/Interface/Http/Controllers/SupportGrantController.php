<?php

namespace Src\Organization\Interface\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Organization\Application\Support\GrantSupportAccess;
use Src\Organization\Application\Support\ListMySupportGroups;
use Src\Organization\Application\Support\ListSupportAdmins;
use Src\Organization\Application\Support\ListSupportGrants;
use Src\Organization\Application\Support\RevokeSupportAccess;
use Src\Organization\Interface\Http\Requests\GrantSupportAccessRequest;
use Src\Organization\Interface\Http\Resources\SupportGrantResource;
use Src\Shared\Application\Accounts\AdminSummary;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Temporary support access: a group admin (`group.members.manage`) gives it and ends it; a platform admin lists the groups that gave it to them. */
final class SupportGrantController extends ApiController
{
    public function admins(Request $request, ActorResolver $actors, ListSupportAdmins $list): JsonResponse
    {
        return response()->json(['data' => array_map(
            static fn (AdminSummary $admin): array => ['id' => $admin->id, 'name' => $admin->name], $list->execute($actors->fromRequest($request)),
        )]);
    }

    public function index(Request $request, ActorResolver $actors, ListSupportGrants $list): AnonymousResourceCollection
    {
        return SupportGrantResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(GrantSupportAccessRequest $request, ActorResolver $actors, GrantSupportAccess $grant): JsonResponse
    {
        $view = $grant->execute($actors->fromRequest($request), $request->adminId(), $request->hours(), $request->ip(), $request->userAgent());

        return (new SupportGrantResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, RevokeSupportAccess $revoke): Response
    {
        $revoke->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function groupsOfAdmin(Request $request, ActorResolver $actors, ListMySupportGroups $list): JsonResponse
    {
        return response()->json(['data' => array_map(static fn (array $row): array => [
            'group_id' => $row['group']->id,
            'name' => $row['group']->name,
            'status' => $row['group']->status->value,
            'expires_at' => $row['grant']->expiresAt->format(DateTimeInterface::ATOM),
        ], $list->execute($actors->fromRequest($request)))]);
    }
}
