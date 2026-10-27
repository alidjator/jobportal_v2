<?php

namespace Src\Dashboard\Interface\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Dashboard\Application\GetAdminDashboard;
use Src\Dashboard\Application\GetCompanyCalendar;
use Src\Dashboard\Application\GetCompanyDashboard;
use Src\Dashboard\Application\GetCompanyTasks;
use Src\Dashboard\Application\GetJobSeekerDashboard;
use Src\Dashboard\Interface\Http\Payloads\CompanyTasksPayload;
use Src\Dashboard\Interface\Http\Requests\CompanyCalendarRequest;
use Src\Dashboard\Interface\Http\Resources\AdminDashboardResource;
use Src\Dashboard\Interface\Http\Resources\CalendarEventResource;
use Src\Dashboard\Interface\Http\Resources\CompanyDashboardResource;
use Src\Dashboard\Interface\Http\Resources\CompanyTasksResource;
use Src\Dashboard\Interface\Http\Resources\JobSeekerDashboardResource;
use Src\Shared\Application\Calendar\CalendarEvent;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** One summary document per kind of account; the details stay on the endpoints of each context. */
final class DashboardController extends ApiController
{
    public function jobSeeker(Request $request, ActorResolver $actors, GetJobSeekerDashboard $get): JobSeekerDashboardResource
    {
        return new JobSeekerDashboardResource($get->execute($actors->fromRequest($request)));
    }

    public function company(Request $request, ActorResolver $actors, GetCompanyDashboard $get): CompanyDashboardResource
    {
        return new CompanyDashboardResource($get->execute($actors->fromRequest($request)));
    }

    public function tasks(Request $request, ActorResolver $actors, GetCompanyTasks $get): CompanyTasksResource
    {
        return new CompanyTasksResource(CompanyTasksPayload::of($get->execute($actors->fromRequest($request))));
    }

    /** The recruitment calendar: interviews, test due times, offer deadlines and offer start dates in a range of at most 42 days. */
    public function calendar(CompanyCalendarRequest $request, ActorResolver $actors, GetCompanyCalendar $get): JsonResponse
    {
        $result = $get->execute($actors->fromRequest($request), $request->string('from')->toString(), $request->string('to')->toString(), $request->types(), $request->jobId(), $request->mine());

        return response()->json([
            'data' => array_map(static fn (CalendarEvent $event): array => (new CalendarEventResource($event))->toArray($request), $result['events']),
            'meta' => ['truncated' => $result['truncated']],
        ]);
    }

    public function admin(Request $request, ActorResolver $actors, Gate $gate, DashboardCards $cards, GetAdminDashboard $get): AdminDashboardResource
    {
        $admin = $request->user('admin');
        $held = array_values(array_filter(
            $cards->permissionsFor(DashboardAudience::Admin),
            fn (string $permission): bool => $gate->forUser($admin)->allows($permission),
        ));

        return new AdminDashboardResource($get->execute($actors->fromRequest($request), $held));
    }
}
