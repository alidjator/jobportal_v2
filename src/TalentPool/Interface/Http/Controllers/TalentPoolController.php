<?php

namespace Src\TalentPool\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Src\TalentPool\Application\ChangeEntry;
use Src\TalentPool\Application\ListEntries;
use Src\TalentPool\Application\RemoveEntry;
use Src\TalentPool\Application\SaveCandidate;
use Src\TalentPool\Interface\Http\Requests\ChangeEntryRequest;
use Src\TalentPool\Interface\Http\Requests\ListEntriesRequest;
use Src\TalentPool\Interface\Http\Requests\SaveCandidateRequest;
use Src\TalentPool\Interface\Http\Resources\EntryResource;

/** The candidates a company saved. */
final class TalentPoolController extends ApiController
{
    public function index(ListEntriesRequest $request, ActorResolver $actors, ListEntries $list): AnonymousResourceCollection
    {
        return EntryResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->categoryId(), $request->pageRequest()), $request));
    }

    public function store(SaveCandidateRequest $request, ActorResolver $actors, SaveCandidate $save): JsonResponse
    {
        $entry = $save->execute($actors->fromRequest($request), $request->candidate(), $request->categoryId(), $request->note());

        return (new EntryResource($entry))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ChangeEntryRequest $request, int $id, ActorResolver $actors, ChangeEntry $change): EntryResource
    {
        return new EntryResource($change->execute($actors->fromRequest($request), $id, $request->changes()));
    }

    /** Removes the candidate from the pool; removing one that is not there is not an error. */
    public function destroy(Request $request, int $id, ActorResolver $actors, RemoveEntry $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
