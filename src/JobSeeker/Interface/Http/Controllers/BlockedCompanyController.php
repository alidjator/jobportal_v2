<?php

namespace Src\JobSeeker\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\JobSeeker\Application\BlockCompany;
use Src\JobSeeker\Application\ListMyBlockedCompanies;
use Src\JobSeeker\Application\UnblockCompany;
use Src\JobSeeker\Interface\Http\Resources\BlockedCompanyResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The companies a job seeker does not want to be found by (at most 20). */
final class BlockedCompanyController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListMyBlockedCompanies $list): AnonymousResourceCollection
    {
        return BlockedCompanyResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function block(Request $request, int $companyId, ActorResolver $actors, BlockCompany $block): Response
    {
        $block->execute($actors->fromRequest($request), $companyId);

        return response()->noContent();
    }

    public function unblock(Request $request, int $companyId, ActorResolver $actors, UnblockCompany $unblock): Response
    {
        $unblock->execute($actors->fromRequest($request), $companyId);

        return response()->noContent();
    }
}
