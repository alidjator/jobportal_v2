<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Admin\ListPsychologists;
use Src\Assessment\Application\Admin\ResetPsychologistTwoFactor;
use Src\Assessment\Application\Admin\RestorePsychologist;
use Src\Assessment\Application\Admin\SuspendPsychologist;
use Src\Assessment\Application\Admin\UpdatePsychologistCredentials;
use Src\Assessment\Interface\Http\Requests\ListPsychologistsRequest;
use Src\Assessment\Interface\Http\Requests\UpdatePsychologistCredentialsRequest;
use Src\Assessment\Interface\Http\Resources\PsychologistResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Psychologists for platform admins holding `assessment_providers.manage`. */
final class AdminPsychologistController extends ApiController
{
    public function index(ListPsychologistsRequest $request, int $id, ActorResolver $actors, ListPsychologists $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $id, $request->pageRequest());

        return PsychologistResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function update(UpdatePsychologistCredentialsRequest $request, int $id, ActorResolver $actors, UpdatePsychologistCredentials $update): PsychologistResource
    {
        $item = $update->execute(
            $actors->fromRequest($request), $id, $request->strNumber(), $request->strValidUntil(), $request->silpNumber(),
            $request->silpValidUntil(), $request->role(), $request->ip(), $request->userAgent(),
        );

        return new PsychologistResource($item);
    }

    public function suspend(Request $request, int $id, ActorResolver $actors, SuspendPsychologist $suspend): Response
    {
        $suspend->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function restore(Request $request, int $id, ActorResolver $actors, RestorePsychologist $restore): Response
    {
        $restore->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function resetTwoFactor(Request $request, int $id, ActorResolver $actors, ResetPsychologistTwoFactor $reset): Response
    {
        $reset->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
