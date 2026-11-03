<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Assessment\Application\Archive\GetMyRetainedData;
use Src\Assessment\Interface\Http\Resources\MyRetainedDataResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** What the erasure of the person's account would keep (`docs/design/phase-8-provider-institutions.md` section 7), shown before they confirm it. */
final class MyRetainedDataController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, GetMyRetainedData $get): MyRetainedDataResource
    {
        return new MyRetainedDataResource($get->execute($actors->fromRequest($request)));
    }
}
