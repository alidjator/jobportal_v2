<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Authoring\ChangeTestArchive;
use Src\Assessment\Application\Authoring\CloneTest;
use Src\Assessment\Application\Authoring\CreateTest;
use Src\Assessment\Application\Authoring\DeleteTest;
use Src\Assessment\Application\Authoring\GetTest;
use Src\Assessment\Application\Authoring\ListTests;
use Src\Assessment\Application\Authoring\PublishTest;
use Src\Assessment\Application\Authoring\UpdateTest;
use Src\Assessment\Interface\Http\Requests\ListTestsRequest;
use Src\Assessment\Interface\Http\Requests\TestRequest;
use Src\Assessment\Interface\Http\Resources\TestDetailsResource;
use Src\Assessment\Interface\Http\Resources\TestResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/**
 * The tests of whoever writes them: a company admin (skill tests of the company), a psychologist (batteries of the provider) or an admin (the
 * system library). The same actions are routed under three prefixes; the use cases decide who the author is from the account.
 */
final class TestController extends ApiController
{
    public function index(ListTestsRequest $request, ActorResolver $actors, ListTests $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->status(), $request->search(), $request->pageRequest());

        return TestResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetTest $get): TestDetailsResource
    {
        return new TestDetailsResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(TestRequest $request, ActorResolver $actors, CreateTest $create): JsonResponse
    {
        $test = $create->execute($actors->fromRequest($request), $request->title(), $request->description(), $request->instructions(), $request->capabilities());

        return (new TestResource($test))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(TestRequest $request, int $id, ActorResolver $actors, UpdateTest $update): TestResource
    {
        return new TestResource($update->execute($actors->fromRequest($request), $id, $request->title(), $request->description(), $request->instructions(), $request->capabilities()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteTest $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function publish(Request $request, int $id, ActorResolver $actors, PublishTest $publish): TestResource
    {
        return new TestResource($publish->execute($actors->fromRequest($request), $id));
    }

    public function archive(Request $request, int $id, ActorResolver $actors, ChangeTestArchive $change): TestResource
    {
        return new TestResource($change->execute($actors->fromRequest($request), $id, true));
    }

    public function restore(Request $request, int $id, ActorResolver $actors, ChangeTestArchive $change): TestResource
    {
        return new TestResource($change->execute($actors->fromRequest($request), $id, false));
    }

    public function duplicate(Request $request, int $id, ActorResolver $actors, CloneTest $clone): JsonResponse
    {
        return (new TestResource($clone->execute($actors->fromRequest($request), $id)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
