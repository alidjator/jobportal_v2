<?php

namespace Src\Notification\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Notification\Application\ListNotifications;
use Src\Notification\Application\PushResult;
use Src\Notification\Application\SendPushToAllUsers;
use Src\Notification\Application\SendPushToUser;
use Src\Notification\Application\StoreDeviceToken;
use Src\Notification\Interface\Http\Requests\BroadcastPushRequest;
use Src\Notification\Interface\Http\Requests\PushMessageRequest;
use Src\Notification\Interface\Http\Requests\StoreTokenRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

class NotificationController extends ApiController
{
    public function index(Request $request, ListNotifications $list): JsonResponse
    {
        // Bentuk lama: respondWithSuccess(['data' => $paginator]) => {data: <paginator>}
        return ApiResponse::success($list((int) $request->user()->getAuthIdentifier()));
    }

    public function storeToken(StoreTokenRequest $request, StoreDeviceToken $store): JsonResponse
    {
        return ApiResponse::success($store((int) $request->user()->getAuthIdentifier(), $request->string('token')->toString()));
    }

    public function storeTokenAnonymous(StoreTokenRequest $request, StoreDeviceToken $store): JsonResponse
    {
        return ApiResponse::success($store(null, $request->string('token')->toString()));
    }

    public function send(PushMessageRequest $request, SendPushToUser $send): JsonResponse
    {
        return $this->respond($send(
            (int) $request->user()->getAuthIdentifier(),
            $request->string('title')->toString(),
            $request->string('body')->toString(),
        ));
    }

    public function sendToAll(BroadcastPushRequest $request, SendPushToAllUsers $send): JsonResponse
    {
        return $this->respond($send($request->string('title')->toString(), $request->string('body')->toString()));
    }

    private function respond(PushResult $result): JsonResponse
    {
        return response()->json(['data' => $result->data], $result->status);
    }
}
