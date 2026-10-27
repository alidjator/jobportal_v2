<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Assessment\Application\Admin\ListProviderRegister;
use Src\Assessment\Interface\Http\Requests\ListRegisterRequest;
use Src\Assessment\Interface\Http\Resources\RegisterItemResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The report register of one provider, for platform admins holding `assessment_providers.manage`. */
final class AdminProviderRegisterController extends ApiController
{
    public function index(ListRegisterRequest $request, int $id, ActorResolver $actors, ListProviderRegister $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $id, $request->registerFilter(), $request->pageRequest());

        return RegisterItemResource::collection(PaginatedResources::paginator($page, $request));
    }
}
