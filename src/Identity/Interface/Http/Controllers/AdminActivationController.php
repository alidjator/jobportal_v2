<?php

namespace Src\Identity\Interface\Http\Controllers;

use Src\Identity\Application\Activation\GetActivationProgress;
use Src\Identity\Interface\Http\Payloads\ActivationProgressPayload;
use Src\Identity\Interface\Http\Requests\ActivationReportRequest;
use Src\Identity\Interface\Http\Resources\ActivationProgressResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** How far the activation of the accounts imported from karir.pkp has come, for admins holding `users.view`. */
final class AdminActivationController extends ApiController
{
    public function report(ActivationReportRequest $request, ActorResolver $actors, GetActivationProgress $progress): ActivationProgressResource
    {
        return new ActivationProgressResource(new ActivationProgressPayload($progress->execute($actors->fromRequest($request), $request->audience())));
    }
}
