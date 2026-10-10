<?php

namespace Src\Campaign\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Campaign\Application\CreateCampaign;
use Src\Campaign\Application\DeleteCampaign;
use Src\Campaign\Application\GetCampaign;
use Src\Campaign\Application\GetCampaignResults;
use Src\Campaign\Application\ListCampaigns;
use Src\Campaign\Application\RenderCampaignQr;
use Src\Campaign\Application\UpdateCampaign;
use Src\Campaign\Interface\Http\Requests\ListCampaignsRequest;
use Src\Campaign\Interface\Http\Requests\SaveCampaignRequest;
use Src\Campaign\Interface\Http\Resources\CampaignResource;
use Src\Campaign\Interface\Http\Resources\CampaignResultsResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/**
 * The campaigns of a company (`company/campaigns`, company admins only) and of the whole platform (`admin/campaigns`, admins with `campaigns.manage`): the same use
 * cases serve both, and the actor decides the scope. A change answers with the whole campaign.
 */
final class CampaignController extends ApiController
{
    public function index(ListCampaignsRequest $request, ActorResolver $actors, ListCampaigns $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->companyId(), $request->search(), $request->type(), $request->status(), $request->pageRequest());

        return CampaignResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetCampaign $get): CampaignResource
    {
        return new CampaignResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(SaveCampaignRequest $request, ActorResolver $actors, CreateCampaign $create, GetCampaign $get): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $campaign = $create->execute($actor, $request->code(), $request->fields(), $request->companyId(), $request->ip(), $request->userAgent());

        return (new CampaignResource($get->execute($actor, (int) $campaign->id)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(SaveCampaignRequest $request, int $id, ActorResolver $actors, UpdateCampaign $update, GetCampaign $get): CampaignResource
    {
        $actor = $actors->fromRequest($request);
        $update->execute($actor, $id, $request->fields(), $request->ip(), $request->userAgent());

        return new CampaignResource($get->execute($actor, $id));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteCampaign $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function results(Request $request, int $id, ActorResolver $actors, GetCampaignResults $get): CampaignResultsResource
    {
        return new CampaignResultsResource($get->execute($actors->fromRequest($request), $id));
    }

    /** The QR code of the registration link as an SVG image; it needs the bearer token like every other call, so a client fetches it and shows the result. */
    public function qr(Request $request, int $id, ActorResolver $actors, RenderCampaignQr $render): Response
    {
        return new Response($render->execute($actors->fromRequest($request), $id), Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
    }
}
