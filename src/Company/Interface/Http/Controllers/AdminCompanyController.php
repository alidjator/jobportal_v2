<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Company\Application\CreateCompany;
use Src\Company\Interface\Http\Requests\CreateCompanyRequest;
use Src\Company\Interface\Http\Resources\CreatedCompanyResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

final class AdminCompanyController extends ApiController
{
    public function store(CreateCompanyRequest $request, RequestContext $context, ActorResolver $actors, CreateCompany $create): JsonResponse
    {
        $created = $create->execute($actors->fromRequest($request), $request->toCommand($context));

        return (new CreatedCompanyResource($created))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
