<?php

namespace Src\TalentPool\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Src\TalentPool\Application\ChangeEntry;
use Src\TalentPool\Application\GetEntry;
use Src\TalentPool\Application\ListEntries;
use Src\TalentPool\Application\RemoveEntry;
use Src\TalentPool\Application\SaveApplicant;
use Src\TalentPool\Application\SaveCandidate;
use Src\TalentPool\Interface\Http\Requests\ChangeEntryRequest;
use Src\TalentPool\Interface\Http\Requests\ListEntriesRequest;
use Src\TalentPool\Interface\Http\Requests\SaveCandidateRequest;
use Src\TalentPool\Interface\Http\Resources\EntryDetailResource;
use Src\TalentPool\Interface\Http\Resources\EntryResource;

/** The people a company saved. */
final class TalentPoolController extends ApiController
{
    public function index(ListEntriesRequest $request, RequestContext $context, ActorResolver $actors, ListEntries $list): AnonymousResourceCollection
    {
        return EntryResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->toFilter(), $request->pageRequest(), $context->language), $request));
    }

    public function show(Request $request, int $id, RequestContext $context, ActorResolver $actors, GetEntry $get): EntryDetailResource
    {
        return new EntryDetailResource($get->execute($actors->fromRequest($request), $id, $context->language));
    }

    /** Saves a findable candidate (`candidate`) or the applicant of an application (`application_id`). */
    public function store(SaveCandidateRequest $request, RequestContext $context, ActorResolver $actors, SaveCandidate $saveCandidate, SaveApplicant $saveApplicant): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $applicationId = $request->applicationId();

        $entry = $applicationId === null
            ? $saveCandidate->execute($actor, (string) $request->candidate(), $request->categoryId(), $request->note(), $context->language)
            : $saveApplicant->execute($actor, $applicationId, $request->categoryId(), $request->note(), $context->language);

        return (new EntryResource($entry))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ChangeEntryRequest $request, int $id, RequestContext $context, ActorResolver $actors, ChangeEntry $change): EntryResource
    {
        return new EntryResource($change->execute($actors->fromRequest($request), $id, $request->changes(), $context->language));
    }

    /** Removes the candidate from the pool; removing one that is not there is not an error. */
    public function destroy(Request $request, int $id, ActorResolver $actors, RemoveEntry $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
