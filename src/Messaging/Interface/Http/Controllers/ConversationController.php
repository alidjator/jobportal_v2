<?php

namespace Src\Messaging\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Messaging\Application\CloseConversation;
use Src\Messaging\Application\CountUnreadConversations;
use Src\Messaging\Application\GetConversation;
use Src\Messaging\Application\ListConversations;
use Src\Messaging\Application\MarkConversationRead;
use Src\Messaging\Application\ReopenConversation;
use Src\Messaging\Application\StartCompanyConversation;
use Src\Messaging\Application\StartedConversation;
use Src\Messaging\Application\StartSeekerConversation;
use Src\Messaging\Interface\Http\Requests\ListMessagingPageRequest;
use Src\Messaging\Interface\Http\Requests\StartCompanyConversationRequest;
use Src\Messaging\Interface\Http\Requests\StartSeekerConversationRequest;
use Src\Messaging\Interface\Http\Resources\ConversationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;
use Symfony\Component\HttpFoundation\Response;

/** The conversations of the caller, on the company side (`company/conversations`) and the job seeker's side (`me/conversations`); who the caller is decides the side. */
final class ConversationController extends ApiController
{
    public function index(ListMessagingPageRequest $request, ActorResolver $actors, ListConversations $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->cursorRequest());

        return ConversationResource::collection($page->items)->additional(CursorResources::envelope($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetConversation $get): ConversationResource
    {
        return new ConversationResource($get->execute($actors->fromRequest($request), $id));
    }

    public function unreadCount(Request $request, ActorResolver $actors, CountUnreadConversations $count): JsonResponse
    {
        return new JsonResponse(['data' => ['unread_conversations' => $count->execute($actors->fromRequest($request))]]);
    }

    public function startAsCompany(StartCompanyConversationRequest $request, ActorResolver $actors, StartCompanyConversation $start): JsonResponse
    {
        return $this->started($start->execute($actors->fromRequest($request), $request->jobSeekerUserId(), $request->toInput()));
    }

    public function startAsSeeker(StartSeekerConversationRequest $request, ActorResolver $actors, StartSeekerConversation $start): JsonResponse
    {
        return $this->started($start->execute($actors->fromRequest($request), $request->companyId(), $request->toInput()));
    }

    public function read(Request $request, int $id, ActorResolver $actors, MarkConversationRead $read): Response
    {
        $read->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function close(Request $request, int $id, ActorResolver $actors, CloseConversation $close): ConversationResource
    {
        return new ConversationResource($close->execute($actors->fromRequest($request), $id));
    }

    public function reopen(Request $request, int $id, ActorResolver $actors, ReopenConversation $reopen): ConversationResource
    {
        return new ConversationResource($reopen->execute($actors->fromRequest($request), $id));
    }

    /** 201 for a new conversation, 200 when the pair already had one and the message went into it. */
    private function started(StartedConversation $started): JsonResponse
    {
        return (new ConversationResource($started->view))->response()->setStatusCode($started->created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
