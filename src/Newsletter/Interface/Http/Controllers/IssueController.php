<?php

namespace Src\Newsletter\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Newsletter\Application\CreateIssue;
use Src\Newsletter\Application\DeleteIssue;
use Src\Newsletter\Application\GetIssue;
use Src\Newsletter\Application\ListIssues;
use Src\Newsletter\Application\SendIssue;
use Src\Newsletter\Application\SendIssueTest;
use Src\Newsletter\Application\UpdateIssue;
use Src\Newsletter\Interface\Http\Requests\ListIssuesRequest;
use Src\Newsletter\Interface\Http\Requests\SaveIssueRequest;
use Src\Newsletter\Interface\Http\Resources\IssueResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Src\Shared\Interface\Http\RequestLanguage;

/** The issues of the newsletter, for admins with `newsletter.manage`. A change answers with the whole issue. */
final class IssueController extends ApiController
{
    public function index(ListIssuesRequest $request, ActorResolver $actors, ListIssues $list): AnonymousResourceCollection
    {
        return IssueResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->status(), $request->pageRequest()), $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetIssue $get): IssueResource
    {
        return new IssueResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(SaveIssueRequest $request, ActorResolver $actors, CreateIssue $create): JsonResponse
    {
        $issue = $create->execute($actors->fromRequest($request), $request->subject(), $request->body());

        return (new IssueResource($issue))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(SaveIssueRequest $request, int $id, ActorResolver $actors, UpdateIssue $update): IssueResource
    {
        return new IssueResource($update->execute($actors->fromRequest($request), $id, $request->subject(), $request->body()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteIssue $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    /** Sends the issue to the admin's own email. */
    public function test(Request $request, int $id, ActorResolver $actors, SendIssueTest $test): Response
    {
        $test->execute($actors->fromRequest($request), $id, RequestLanguage::of($request));

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    /** Starts the real send; the figures of the issue fill in as the queue works. */
    public function send(Request $request, int $id, ActorResolver $actors, SendIssue $send, GetIssue $get): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $send->execute($actor, $id, $request->ip(), $request->userAgent());

        return (new IssueResource($get->execute($actor, $id)))->response()->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }
}
