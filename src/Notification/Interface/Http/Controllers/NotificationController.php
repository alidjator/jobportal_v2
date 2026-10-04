<?php

namespace Src\Notification\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Notification\Application\InApp\CountUnreadNotifications;
use Src\Notification\Application\InApp\DeleteNotification;
use Src\Notification\Application\InApp\ListNotifications;
use Src\Notification\Application\InApp\MarkAllNotificationsRead;
use Src\Notification\Application\InApp\MarkNotificationRead;
use Src\Notification\Interface\Http\Requests\ListNotificationsRequest;
use Src\Notification\Interface\Http\Resources\NotificationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;

/** The signed-in user's own in-app notifications. */
final class NotificationController extends ApiController
{
    public function index(ListNotificationsRequest $request, ActorResolver $actors, ListNotifications $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->readFilter(), $request->cursorRequest());

        return CursorResources::of($page, NotificationResource::class, $request);
    }

    public function unreadCount(Request $request, ActorResolver $actors, CountUnreadNotifications $count): JsonResponse
    {
        return new JsonResponse(['data' => ['count' => $count->execute($actors->fromRequest($request))]]);
    }

    public function markRead(Request $request, string $id, ActorResolver $actors, MarkNotificationRead $markRead): Response
    {
        $markRead->execute($actors->fromRequest($request), $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function markAllRead(Request $request, ActorResolver $actors, MarkAllNotificationsRead $markAllRead): Response
    {
        $markAllRead->execute($actors->fromRequest($request));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function destroy(Request $request, string $id, ActorResolver $actors, DeleteNotification $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
