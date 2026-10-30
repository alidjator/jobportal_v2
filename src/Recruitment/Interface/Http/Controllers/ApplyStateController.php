<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Recruitment\Application\GetApplyState;
use Src\Recruitment\Interface\Http\Resources\ApplyStateResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** What stands between the job seeker and an application to a live job (`docs/design/phase-8-job-posting.md` section 6). `{job}` is the id, optionally followed by the cosmetic slug. */
final class ApplyStateController extends ApiController
{
    public function __invoke(Request $request, string $job, ActorResolver $actors, GetApplyState $state): JsonResponse
    {
        return (new ApplyStateResource($state->execute($actors->fromRequest($request), (int) $job)))->response()->header('Cache-Control', 'no-store, private');
    }
}
