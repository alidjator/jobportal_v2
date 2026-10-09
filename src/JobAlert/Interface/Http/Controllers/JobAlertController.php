<?php

namespace Src\JobAlert\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\JobAlert\Application\CreateJobAlert;
use Src\JobAlert\Application\DeleteJobAlert;
use Src\JobAlert\Application\GetJobAlert;
use Src\JobAlert\Application\ListJobAlerts;
use Src\JobAlert\Application\PauseJobAlert;
use Src\JobAlert\Application\ResumeJobAlert;
use Src\JobAlert\Application\UpdateJobAlert;
use Src\JobAlert\Interface\Http\Requests\JobAlertFieldsRequest;
use Src\JobAlert\Interface\Http\Resources\JobAlertResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Symfony\Component\HttpFoundation\Response;

/** The job alerts of the signed-in job seeker. An alert that is missing or someone else's is a 404. */
final class JobAlertController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListJobAlerts $list): AnonymousResourceCollection
    {
        return JobAlertResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(JobAlertFieldsRequest $request, ActorResolver $actors, CreateJobAlert $create): JsonResponse
    {
        return (new JobAlertResource($create->execute($actors->fromRequest($request), $request->toInput())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetJobAlert $get): JobAlertResource
    {
        return new JobAlertResource($get->execute($actors->fromRequest($request), $id));
    }

    public function update(JobAlertFieldsRequest $request, int $id, ActorResolver $actors, UpdateJobAlert $update): JobAlertResource
    {
        return new JobAlertResource($update->execute($actors->fromRequest($request), $id, $request->toInput()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteJobAlert $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function pause(Request $request, int $id, ActorResolver $actors, PauseJobAlert $pause): JobAlertResource
    {
        return new JobAlertResource($pause->execute($actors->fromRequest($request), $id));
    }

    public function resume(Request $request, int $id, ActorResolver $actors, ResumeJobAlert $resume): JobAlertResource
    {
        return new JobAlertResource($resume->execute($actors->fromRequest($request), $id));
    }
}
