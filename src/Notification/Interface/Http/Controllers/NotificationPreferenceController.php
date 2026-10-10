<?php

namespace Src\Notification\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Notification\Application\Preferences\ListNotificationPreferences;
use Src\Notification\Application\Preferences\NotificationPreferenceView;
use Src\Notification\Application\Preferences\UpdateNotificationPreference;
use Src\Notification\Interface\Http\Requests\UpdateNotificationPreferenceRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The signed-in user's email and push preferences. In-app notifications are always delivered and have none. */
final class NotificationPreferenceController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListNotificationPreferences $list): JsonResponse
    {
        return new JsonResponse(['data' => array_map(
            fn (NotificationPreferenceView $view): array => [
                'key' => $view->key,
                'description' => $view->description,
                'channels' => (object) $view->channels,
            ],
            $list->execute($actors->fromRequest($request)),
        )]);
    }

    public function update(UpdateNotificationPreferenceRequest $request, string $key, string $channel, ActorResolver $actors, UpdateNotificationPreference $update): Response
    {
        $update->execute($actors->fromRequest($request), $key, $channel, $request->enabled());

        return response()->noContent();
    }
}
