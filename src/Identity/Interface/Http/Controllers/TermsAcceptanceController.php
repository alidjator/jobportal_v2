<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\Application\Terms\AcceptTerms;
use Src\Identity\Application\Terms\GetTermsStatus;
use Src\Identity\Interface\Http\Payloads\TermsStatusPayload;
use Src\Identity\Interface\Http\Requests\AcceptTermsRequest;
use Src\Identity\Interface\Http\Resources\TermsStatusResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The terms gate's own endpoints: both are exempt from the gate, otherwise nobody could ever accept. */
final class TermsAcceptanceController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetTermsStatus $getStatus): TermsStatusResource
    {
        $requirements = $getStatus->execute($actors->fromRequest($request));

        return new TermsStatusResource(new TermsStatusPayload($requirements));
    }

    public function store(AcceptTermsRequest $request, RequestContext $context, ActorResolver $actors, AcceptTerms $acceptTerms): Response
    {
        $acceptTerms->execute($actors->fromRequest($request), $request->versions(), $context->language, $request->ip());

        return response()->noContent();
    }
}
