<?php

namespace Src\Dashboard\Interface\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Dashboard\Application\GetAdminDashboard;
use Src\Dashboard\Application\GetCompanyDashboard;
use Src\Dashboard\Application\GetCompanyTasks;
use Src\Dashboard\Application\GetJobSeekerDashboard;
use Src\Dashboard\Interface\Http\Resources\DashboardResource;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** One summary document per kind of account; the details stay on the endpoints of each context. */
final class DashboardController extends ApiController
{
    public function jobSeeker(Request $request, ActorResolver $actors, GetJobSeekerDashboard $get): DashboardResource
    {
        return new DashboardResource($get->execute($actors->fromRequest($request)));
    }

    public function company(Request $request, ActorResolver $actors, GetCompanyDashboard $get): DashboardResource
    {
        return new DashboardResource($get->execute($actors->fromRequest($request)));
    }

    public function tasks(Request $request, ActorResolver $actors, GetCompanyTasks $get): JsonResponse
    {
        return response()->json(['data' => $get->execute($actors->fromRequest($request))]);
    }

    public function admin(Request $request, ActorResolver $actors, Gate $gate, DashboardCards $cards, GetAdminDashboard $get): DashboardResource
    {
        $admin = $request->user('admin');
        $held = array_values(array_filter(
            $cards->permissionsFor(DashboardAudience::Admin),
            fn (string $permission): bool => $gate->forUser($admin)->allows($permission),
        ));

        return new DashboardResource($get->execute($actors->fromRequest($request), $held));
    }
}
