<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Assessment\Application\Provider\GetMyProfile;
use Src\Assessment\Interface\Http\Resources\PsychologistProfileResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The signed-in psychologist's own profile. */
final class ProviderProfileController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, GetMyProfile $get): PsychologistProfileResource
    {
        return new PsychologistProfileResource($get->execute($actors->fromRequest($request)));
    }
}
