<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\GetCompanyInterview;
use Src\Recruitment\Application\GetInterviewCalendarFile;
use Src\Recruitment\Application\ListApplicationInterviews;
use Src\Recruitment\Application\ListInterviewers;
use Src\Recruitment\Application\ListMyInterviews;
use Src\Recruitment\Application\ManageInterview;
use Src\Recruitment\Application\ScheduleInterview;
use Src\Recruitment\Interface\Http\Requests\ListMyInterviewsRequest;
use Src\Recruitment\Interface\Http\Requests\MarkInterviewNoShowRequest;
use Src\Recruitment\Interface\Http\Requests\RefuseInterviewProposalRequest;
use Src\Recruitment\Interface\Http\Requests\ScheduleInterviewRequest;
use Src\Recruitment\Interface\Http\Requests\UpdateInterviewNoteRequest;
use Src\Recruitment\Interface\Http\Resources\CompanyInterviewDetailResource;
use Src\Recruitment\Interface\Http\Resources\CompanyInterviewResource;
use Src\Recruitment\Interface\Http\Resources\InterviewerResource;
use Src\Recruitment\Interface\Http\Resources\MyInterviewResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;

/** The interviews of an application, for the members who may see its job. */
final class InterviewController extends ApiController
{
    /** The interviews of the caller: the ones they sit on, upcoming or past, or every interview of the jobs they may see. */
    public function mine(ListMyInterviewsRequest $request, ActorResolver $actors, ListMyInterviews $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->upcoming(), $request->scorecardPending(), $request->rsvp(), $request->allOfCompany(), $request->cursorRequest(), $request->proposalOpen());

        return MyInterviewResource::collection($page->items)->additional(CursorResources::envelope($page, $request));
    }

    /** One interview with its panel, the job and the candidate, for the interview page (the links of notifications and emails carry only its id). */
    public function show(Request $request, int $id, ActorResolver $actors, GetCompanyInterview $get): CompanyInterviewDetailResource
    {
        return new CompanyInterviewDetailResource($get->execute($actors->fromRequest($request), $id));
    }

    public function index(Request $request, int $id, ActorResolver $actors, ListApplicationInterviews $list): AnonymousResourceCollection
    {
        return CompanyInterviewResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    /** Schedules the interview: 201 for a new one, 200 when it replaced the active one. */
    public function store(ScheduleInterviewRequest $request, int $id, ActorResolver $actors, ScheduleInterview $schedule): JsonResponse
    {
        $result = $schedule->execute($actors->fromRequest($request), $id, $request->toInput());

        return (new CompanyInterviewResource($result['view']))->response()->setStatusCode($result['replaced'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    /** The members who may be chosen as main interviewer or panel member, by name, not paginated (`interviews.manage`). */
    public function interviewers(Request $request, ActorResolver $actors, ListInterviewers $list): AnonymousResourceCollection
    {
        return InterviewerResource::collection($list->execute($actors->fromRequest($request)));
    }

    /** Changes only the note for the candidate; the interview is not rescheduled. */
    public function updateNote(UpdateInterviewNoteRequest $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->updateNote($actors->fromRequest($request), $id, $request->note()));
    }

    /** The calendar file of an interview, for the team. */
    public function calendarFile(Request $request, int $id, ActorResolver $actors, GetInterviewCalendarFile $get): Response
    {
        return new Response($get->execute($actors->fromRequest($request), $id), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="interview.ics"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function acceptProposal(Request $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->acceptProposal($actors->fromRequest($request), $id));
    }

    /** Keeps the current time instead of the one the job seeker proposed; the optional message goes to the job seeker and is not stored. */
    public function refuseProposal(RefuseInterviewProposalRequest $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->refuseProposal($actors->fromRequest($request), $id, $request->message()));
    }

    /** Records that the job seeker did not come to the confirmed interview, after its start. */
    public function noShow(MarkInterviewNoShowRequest $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->markNoShow($actors->fromRequest($request), $id, $request->note()));
    }

    public function cancel(Request $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->cancel($actors->fromRequest($request), $id));
    }

    public function complete(Request $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->complete($actors->fromRequest($request), $id));
    }
}
