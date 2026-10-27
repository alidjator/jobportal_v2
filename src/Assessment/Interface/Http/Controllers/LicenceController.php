<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Assessment\Application\Licence\ListMyLicences;
use Src\Assessment\Application\Licence\SubmitLicence;
use Src\Assessment\Interface\Http\Requests\SubmitLicenceRequest;
use Src\Assessment\Interface\Http\Resources\LicenceSubmissionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** A psychologist's own licence submissions: what they sent and how the platform admin decided. */
final class LicenceController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListMyLicences $list): AnonymousResourceCollection
    {
        return LicenceSubmissionResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(SubmitLicenceRequest $request, ActorResolver $actors, SubmitLicence $submit): JsonResponse
    {
        $submission = $submit->execute(
            $actors->fromRequest($request), $request->kind(), $request->string('number')->toString(), $request->validUntil(), $request->contents(), $request->originalName(),
        );

        return (new LicenceSubmissionResource($submission))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
