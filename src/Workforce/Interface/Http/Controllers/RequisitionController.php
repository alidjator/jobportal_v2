<?php

namespace Src\Workforce\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Workforce\Application\Plan\PlanSide;
use Src\Workforce\Application\Requisition\AddRequisitionDocument;
use Src\Workforce\Application\Requisition\AmendRequisition;
use Src\Workforce\Application\Requisition\CancelRequisition;
use Src\Workforce\Application\Requisition\CloseRequisition;
use Src\Workforce\Application\Requisition\CreateJobFromRequisition;
use Src\Workforce\Application\Requisition\CreateRequisition;
use Src\Workforce\Application\Requisition\GetRequisition;
use Src\Workforce\Application\Requisition\GetRequisitionDocument;
use Src\Workforce\Application\Requisition\ListRequisitions;
use Src\Workforce\Application\Requisition\RemoveRequisitionDocument;
use Src\Workforce\Application\Requisition\SubmitRequisition;
use Src\Workforce\Application\Requisition\UpdateRequisition;
use Src\Workforce\Interface\Http\Requests\AmendRequisitionRequest;
use Src\Workforce\Interface\Http\Requests\CloseRequisitionRequest;
use Src\Workforce\Interface\Http\Requests\ListRequisitionsRequest;
use Src\Workforce\Interface\Http\Requests\OpenRequisitionJobRequest;
use Src\Workforce\Interface\Http\Requests\SaveRequisitionRequest;
use Src\Workforce\Interface\Http\Requests\UploadRequisitionDocumentRequest;
use Src\Workforce\Interface\Http\Resources\RequisitionResource;
use Src\Workforce\Interface\Http\Resources\RequisitionSummaryResource;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The requisitions of the signed-in member's own tenant (`company/requisitions`) or, read only, of every legal entity of the group (`organization/requisitions`). */
final class RequisitionController extends ApiController
{
    public function companyIndex(ListRequisitionsRequest $request, ActorResolver $actors, ListRequisitions $list): AnonymousResourceCollection
    {
        return $this->index($request, $actors, $list, PlanSide::Company);
    }

    public function groupIndex(ListRequisitionsRequest $request, ActorResolver $actors, ListRequisitions $list): AnonymousResourceCollection
    {
        return $this->index($request, $actors, $list, PlanSide::Group);
    }

    public function companyShow(Request $request, int $id, ActorResolver $actors, GetRequisition $get): RequisitionResource
    {
        return new RequisitionResource($get->execute($actors->fromRequest($request), PlanSide::Company, $id));
    }

    public function groupShow(Request $request, int $id, ActorResolver $actors, GetRequisition $get): RequisitionResource
    {
        return new RequisitionResource($get->execute($actors->fromRequest($request), PlanSide::Group, $id));
    }

    public function store(SaveRequisitionRequest $request, ActorResolver $actors, CreateRequisition $create): JsonResponse
    {
        $view = $create->execute($actors->fromRequest($request), $request->requisition(), $request->ip(), $request->userAgent());

        return (new RequisitionResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveRequisitionRequest $request, int $id, ActorResolver $actors, UpdateRequisition $update): RequisitionResource
    {
        return new RequisitionResource($update->execute($actors->fromRequest($request), $id, $request->requisition(), $request->ip(), $request->userAgent()));
    }

    public function submit(Request $request, int $id, ActorResolver $actors, SubmitRequisition $submit): RequisitionResource
    {
        return new RequisitionResource($submit->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    public function amend(AmendRequisitionRequest $request, int $id, ActorResolver $actors, AmendRequisition $amend): RequisitionResource
    {
        return new RequisitionResource($amend->execute($actors->fromRequest($request), $id, $request->amendment(), $request->ip(), $request->userAgent()));
    }

    public function close(CloseRequisitionRequest $request, int $id, ActorResolver $actors, CloseRequisition $close): RequisitionResource
    {
        return new RequisitionResource($close->execute($actors->fromRequest($request), $id, $request->reason(), $request->ip(), $request->userAgent()));
    }

    public function cancel(Request $request, int $id, ActorResolver $actors, CancelRequisition $cancel): RequisitionResource
    {
        return new RequisitionResource($cancel->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    /** Opens a draft job for the requisition; the answer holds the id of the job (`GET company/jobs/{id}` shows it). */
    public function openJob(OpenRequisitionJobRequest $request, int $id, ActorResolver $actors, CreateJobFromRequisition $open): JsonResponse
    {
        $jobId = $open->execute($actors->fromRequest($request), $id, $request->title(), $request->vacancies(), $request->ip(), $request->userAgent());

        return response()->json(['data' => ['job_id' => $jobId, 'requisition_id' => $id]], Response::HTTP_CREATED);
    }

    /** Attaches a document (`file`, multipart) to a requisition that is not decided yet; the answer is the requisition with its documents. */
    public function addDocument(UploadRequisitionDocumentRequest $request, int $id, ActorResolver $actors, AddRequisitionDocument $add, GetRequisition $get): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $add->execute($actor, $id, $request->fileContents(), $request->fileName(), $request->ip(), $request->userAgent());

        return (new RequisitionResource($get->execute($actor, PlanSide::Company, $id)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function removeDocument(Request $request, int $id, int $documentId, ActorResolver $actors, RemoveRequisitionDocument $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $id, $documentId, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function companyDocument(Request $request, int $id, int $documentId, ActorResolver $actors, GetRequisitionDocument $get): Response
    {
        return $this->download($get, $actors, $request, PlanSide::Company, $id, $documentId);
    }

    public function groupDocument(Request $request, int $id, int $documentId, ActorResolver $actors, GetRequisitionDocument $get): Response
    {
        return $this->download($get, $actors, $request, PlanSide::Group, $id, $documentId);
    }

    private function download(GetRequisitionDocument $get, ActorResolver $actors, Request $request, PlanSide $side, int $id, int $documentId): Response
    {
        $file = $get->execute($actors->fromRequest($request), $side, $id, $documentId);

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->name, 'document.'.pathinfo($file->name, PATHINFO_EXTENSION)),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    private function index(ListRequisitionsRequest $request, ActorResolver $actors, ListRequisitions $list, PlanSide $side): AnonymousResourceCollection
    {
        return RequisitionSummaryResource::collection(
            $list->execute($actors->fromRequest($request), $side, $request->legalEntityId(), $request->status(), $request->type(), $request->positionId()),
        );
    }
}
