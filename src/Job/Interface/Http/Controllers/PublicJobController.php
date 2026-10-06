<?php

namespace Src\Job\Interface\Http\Controllers;

use Src\Job\Application\Browse\GetPublicJob;
use Src\Job\Interface\Http\Resources\PublicJobResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

/** The public page of a job: no sign-in. `{job}` is the id, optionally followed by the cosmetic slug (`123` or `123-backend-developer`); the slug is ignored. */
final class PublicJobController extends ApiController
{
    public function show(string $job, RequestContext $context, GetPublicJob $get): PublicJobResource
    {
        return new PublicJobResource($get->execute((int) $job, $context->language));
    }
}
