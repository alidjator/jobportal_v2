<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Company\Application\GetCurrentCompany;
use Src\Company\Interface\Http\Resources\CurrentCompanyResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class CurrentCompanyController extends ApiController
{
    public function __invoke(Request $request, ActorResolver $actors, GetCurrentCompany $current): CurrentCompanyResource
    {
        return new CurrentCompanyResource($current->execute($actors->fromRequest($request)));
    }
}
