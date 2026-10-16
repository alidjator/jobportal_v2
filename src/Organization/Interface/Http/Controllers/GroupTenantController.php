<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Organization\Application\Group\LegalEntityView;
use Src\Organization\Application\Reading\ListGroupTenants;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The internal tenants of the signed-in member's group, whose recruitment `group.recruitment.view` lets them read. */
final class GroupTenantController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListGroupTenants $list): JsonResponse
    {
        return response()->json(['data' => array_map(static fn (LegalEntityView $view): array => [
            'company_id' => $view->tenantCompanyId,
            'name' => $view->tenantName,
            'status' => $view->tenantStatus,
            'legal_entity_id' => $view->entity->id,
            'legal_name' => $view->entity->legalName,
        ], $list->execute($actors->fromRequest($request)))]);
    }
}
