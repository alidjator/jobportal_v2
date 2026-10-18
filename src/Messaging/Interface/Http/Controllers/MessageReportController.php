<?php

namespace Src\Messaging\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Messaging\Application\Reports\ReportConversation;
use Src\Messaging\Interface\Http\Requests\ReportConversationRequest;
use Src\Messaging\Interface\Http\Resources\MessageReportReceiptResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** A party of a conversation reports it (`me/conversations/{id}/report` for a job seeker, `company/conversations/{id}/report` for a company member). */
final class MessageReportController extends ApiController
{
    public function store(ReportConversationRequest $request, int $id, ActorResolver $actors, ReportConversation $report): JsonResponse
    {
        $created = $report->execute($actors->fromRequest($request), $id, $request->reason(), $request->note(), $request->messageId());

        return (new MessageReportReceiptResource($created))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
