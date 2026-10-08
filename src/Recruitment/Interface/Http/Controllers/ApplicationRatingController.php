<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Recruitment\Application\RateApplication;
use Src\Recruitment\Interface\Http\Requests\RateApplicationRequest;
use Src\Recruitment\Interface\Http\Resources\ApplicationRatingResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The caller's own rating of an application; the answer is the rating summary after the change. */
final class ApplicationRatingController extends ApiController
{
    public function update(RateApplicationRequest $request, int $id, ActorResolver $actors, RateApplication $rate): ApplicationRatingResource
    {
        return new ApplicationRatingResource($rate->rate($actors->fromRequest($request), $id, $request->stars()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, RateApplication $rate): ApplicationRatingResource
    {
        return new ApplicationRatingResource($rate->withdraw($actors->fromRequest($request), $id));
    }
}
