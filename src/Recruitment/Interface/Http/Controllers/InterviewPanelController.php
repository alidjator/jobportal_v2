<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Src\Recruitment\Application\UpdateInterviewPanel;
use Src\Recruitment\Interface\Http\Requests\UpdateInterviewPanelRequest;
use Src\Recruitment\Interface\Http\Resources\CompanyInterviewResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The extra members of the panel of an existing interview. */
final class InterviewPanelController extends ApiController
{
    public function update(UpdateInterviewPanelRequest $request, int $id, ActorResolver $actors, UpdateInterviewPanel $update): CompanyInterviewResource
    {
        return new CompanyInterviewResource($update->execute($actors->fromRequest($request), $id, $request->participantIds()));
    }
}
