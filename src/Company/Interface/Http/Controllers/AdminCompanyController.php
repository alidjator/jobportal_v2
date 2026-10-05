<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Company\Application\Admin\GetAdminCompany;
use Src\Company\Application\Admin\ListCompanies;
use Src\Company\Application\Admin\RestoreCompany;
use Src\Company\Application\Admin\SuspendCompany;
use Src\Company\Application\CreateCompany;
use Src\Company\Application\InviteCompanyAdminAgain;
use Src\Company\Interface\Http\Requests\CreateCompanyRequest;
use Src\Company\Interface\Http\Requests\InviteCompanyAdminRequest;
use Src\Company\Interface\Http\Requests\ListCompaniesRequest;
use Src\Company\Interface\Http\Requests\RestoreCompanyRequest;
use Src\Company\Interface\Http\Requests\SuspendCompanyRequest;
use Src\Company\Interface\Http\Resources\AdminCompanyDetailResource;
use Src\Company\Interface\Http\Resources\AdminCompanyListResource;
use Src\Company\Interface\Http\Resources\CreatedCompanyResource;
use Src\Company\Interface\Http\Resources\IssuedInvitationResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Companies for platform admins holding `companies.manage`. */
final class AdminCompanyController extends ApiController
{
    public function index(ListCompaniesRequest $request, ActorResolver $actors, ListCompanies $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->toQuery(), $request->pageRequest());

        return PaginatedResources::of($page, AdminCompanyListResource::class, $request);
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetAdminCompany $get): AdminCompanyDetailResource
    {
        return new AdminCompanyDetailResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(CreateCompanyRequest $request, RequestContext $context, ActorResolver $actors, CreateCompany $create): JsonResponse
    {
        $created = $create->execute($actors->fromRequest($request), $request->toCommand($context));

        return (new CreatedCompanyResource($created))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function suspend(SuspendCompanyRequest $request, int $id, ActorResolver $actors, SuspendCompany $suspend): Response
    {
        $suspend->execute($actors->fromRequest($request), $id, $request->reason(), $request->ip(), $request->userAgent());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function restore(RestoreCompanyRequest $request, int $id, ActorResolver $actors, RestoreCompany $restore): Response
    {
        $restore->execute($actors->fromRequest($request), $id, $request->note(), $request->ip(), $request->userAgent());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function inviteCompanyAdmin(InviteCompanyAdminRequest $request, int $id, RequestContext $context, ActorResolver $actors, InviteCompanyAdminAgain $invite): JsonResponse
    {
        $invitation = $invite->execute($actors->fromRequest($request), $id, $request->email(), $context->language, $request->ip(), $request->userAgent());

        return (new IssuedInvitationResource($invitation))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
