<?php

namespace Src\TalentPool\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\TalentPool\Application\RemoveMyLegacyEntries;
use Src\TalentPool\Interface\Http\Requests\RemoveMyMembershipsRequest;

/** The job seeker's side of the company talent pools: leave the private lists carried over from karir.pkp. */
final class MyMembershipController extends ApiController
{
    public function destroy(RemoveMyMembershipsRequest $request, ActorResolver $actors, RemoveMyLegacyEntries $remove): Response
    {
        $remove->execute($actors->fromRequest($request));

        return response()->noContent();
    }
}
