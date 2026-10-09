<?php

namespace Src\Messaging\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Messaging\Application\DeleteMessage;
use Src\Messaging\Application\GetAttachment;
use Src\Messaging\Application\ListMessages;
use Src\Messaging\Application\SendMessage;
use Src\Messaging\Interface\Http\Requests\ListMessagingPageRequest;
use Src\Messaging\Interface\Http\Requests\SendMessageRequest;
use Src\Messaging\Interface\Http\Resources\MessageResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The messages of a conversation, for both sides. */
final class MessageController extends ApiController
{
    public function index(ListMessagingPageRequest $request, int $id, ActorResolver $actors, ListMessages $list): AnonymousResourceCollection
    {
        return CursorResources::of($list->execute($actors->fromRequest($request), $id, $request->cursorRequest()), MessageResource::class, $request);
    }

    public function store(SendMessageRequest $request, int $id, ActorResolver $actors, SendMessage $send): JsonResponse
    {
        return (new MessageResource($send->execute($actors->fromRequest($request), $id, $request->toInput())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteMessage $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** The stored bytes with the type read from the content (never one the client sent), as a download that a browser cannot run as a page. */
    public function attachment(Request $request, int $id, int $attachmentId, ActorResolver $actors, GetAttachment $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $id, $attachmentId);
        $fallback = str_replace(['%', '/', '\\', '"'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $file->name));

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mime,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->name, $fallback),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
