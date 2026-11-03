<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Assessment\Application\Messages\ListCompanyReportMessages;
use Src\Assessment\Application\Messages\ListProviderReportMessages;
use Src\Assessment\Application\Messages\PostCompanyReportMessage;
use Src\Assessment\Application\Messages\PostProviderReportMessage;
use Src\Assessment\Interface\Http\Requests\ReportMessageRequest;
use Src\Assessment\Interface\Http\Resources\ReportThreadResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The questions a company asks about a signed report and the answers of the institution (`docs/design/phase-8-provider-institutions.md` section 6), from both sides. */
final class ReportMessageController extends ApiController
{
    public function companyIndex(Request $request, int $id, ActorResolver $actors, ListCompanyReportMessages $list): ReportThreadResource
    {
        return new ReportThreadResource($list->execute($actors->fromRequest($request), $id));
    }

    public function companyStore(ReportMessageRequest $request, int $id, ActorResolver $actors, PostCompanyReportMessage $post): JsonResponse
    {
        return (new ReportThreadResource($post->execute($actors->fromRequest($request), $id, $request->body())))->response()->setStatusCode(201);
    }

    public function providerIndex(Request $request, int $id, ActorResolver $actors, ListProviderReportMessages $list): ReportThreadResource
    {
        return new ReportThreadResource($list->execute($actors->fromRequest($request), $id));
    }

    public function providerStore(ReportMessageRequest $request, int $id, ActorResolver $actors, PostProviderReportMessage $post): JsonResponse
    {
        return (new ReportThreadResource($post->execute($actors->fromRequest($request), $id, $request->body())))->response()->setStatusCode(201);
    }
}
