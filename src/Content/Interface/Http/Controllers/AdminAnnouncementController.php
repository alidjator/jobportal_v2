<?php

namespace Src\Content\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Content\Application\Announcements\CreateAnnouncement;
use Src\Content\Application\Announcements\DeleteAnnouncement;
use Src\Content\Application\Announcements\GetAnnouncement;
use Src\Content\Application\Announcements\ListAnnouncements;
use Src\Content\Application\Announcements\UpdateAnnouncement;
use Src\Content\Interface\Http\Requests\AnnouncementRequest;
use Src\Content\Interface\Http\Requests\ListAnnouncementsRequest;
use Src\Content\Interface\Http\Resources\AdminAnnouncementResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Announcement management for admins holding `announcements.manage`. */
final class AdminAnnouncementController extends ApiController
{
    public function index(ListAnnouncementsRequest $request, ListAnnouncements $list): AnonymousResourceCollection
    {
        return AdminAnnouncementResource::collection(PaginatedResources::paginator($list->execute($request->pageRequest()), $request));
    }

    public function show(int $id, GetAnnouncement $get): AdminAnnouncementResource
    {
        return new AdminAnnouncementResource($get->execute($id));
    }

    public function store(AnnouncementRequest $request, ActorResolver $actors, CreateAnnouncement $create): JsonResponse
    {
        $announcement = $create->execute($actors->fromRequest($request), $request->announcement(), $request->ip(), $request->userAgent());

        return (new AdminAnnouncementResource($announcement))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(int $id, AnnouncementRequest $request, ActorResolver $actors, UpdateAnnouncement $update): AdminAnnouncementResource
    {
        return new AdminAnnouncementResource($update->execute($actors->fromRequest($request), $id, $request->announcement(), $request->ip(), $request->userAgent()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteAnnouncement $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
