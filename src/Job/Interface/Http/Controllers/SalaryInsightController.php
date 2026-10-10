<?php

namespace Src\Job\Interface\Http\Controllers;

use Src\Job\Application\Insight\GetSalaryInsight;
use Src\Job\Interface\Http\Requests\SalaryInsightRequest;
use Src\Job\Interface\Http\Resources\SalaryInsightResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

/** What the jobs on this platform pay for a role, for anyone: no sign-in is needed. */
final class SalaryInsightController extends ApiController
{
    public function show(SalaryInsightRequest $request, RequestContext $context, GetSalaryInsight $get): SalaryInsightResource
    {
        return new SalaryInsightResource($get->execute($request->insightQuery(), $context->language));
    }
}
