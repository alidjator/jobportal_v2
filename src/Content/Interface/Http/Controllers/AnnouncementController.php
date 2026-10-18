<?php

namespace Src\Content\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Content\Application\Announcements\ListLiveAnnouncements;
use Src\Content\Interface\Http\Resources\AnnouncementResource;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorProvider;
use Src\Shared\Interface\Http\ApiController;

/** The banners for the caller (no sign-in needed; a signed-in job seeker or company member also sees their own audience's). */
final class AnnouncementController extends ApiController
{
    public function index(Request $request, ListLiveAnnouncements $list): AnonymousResourceCollection
    {
        return AnnouncementResource::collection($list->execute($this->viewer($request)));
    }

    /** The account the request's token belongs to, or null (no token, a bad one, or an admin). */
    private function viewer(Request $request): ?Actor
    {
        $account = $request->bearerToken() === null ? null : $request->user('user');

        return $account instanceof ActorProvider ? $account->toActor() : null;
    }
}
