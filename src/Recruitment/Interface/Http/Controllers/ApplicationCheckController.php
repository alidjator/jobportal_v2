<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\Checks\CheckView;
use Src\Recruitment\Application\Checks\GetCheckResultFile;
use Src\Recruitment\Application\Checks\ListApplicationChecks;
use Src\Recruitment\Application\Checks\RecordCheckResult;
use Src\Recruitment\Application\Checks\RequestCheck;
use Src\Recruitment\Application\Checks\UpdateCheck;
use Src\Recruitment\Domain\CheckStatus;
use Src\Recruitment\Interface\Http\PdfDownload;
use Src\Recruitment\Interface\Http\Requests\RecordCheckResultRequest;
use Src\Recruitment\Interface\Http\Requests\RequestCheckRequest;
use Src\Recruitment\Interface\Http\Requests\UpdateCheckRequest;
use Src\Recruitment\Interface\Http\Resources\ApplicationCheckResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The pre-employment checks of an application: requested, changed, closed and recorded by members with `checks.manage`, read by everyone who sees the application. */
final class ApplicationCheckController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListApplicationChecks $list): AnonymousResourceCollection
    {
        return ApplicationCheckResource::collection($list->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    public function store(RequestCheckRequest $request, int $id, ActorResolver $actors, RequestCheck $requestCheck, ListApplicationChecks $list): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $check = $requestCheck->execute($actor, $id, $request->checkTypeId(), $request->scheduledAt(), $request->place(), $request->vendor(), $request->noteToCandidate());

        return (new ApplicationCheckResource($this->view($list->execute($actor, $id), (int) $check->id)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCheckRequest $request, int $id, int $checkId, ActorResolver $actors, UpdateCheck $update, ListApplicationChecks $list): ApplicationCheckResource
    {
        $actor = $actors->fromRequest($request);
        $update->execute($actor, $id, $checkId, $request->scheduledAt(), $request->place(), $request->vendor(), $request->noteToCandidate());

        return new ApplicationCheckResource($this->view($list->execute($actor, $id), $checkId));
    }

    public function waive(Request $request, int $id, int $checkId, ActorResolver $actors, UpdateCheck $update, ListApplicationChecks $list): ApplicationCheckResource
    {
        $actor = $actors->fromRequest($request);
        $update->close($actor, $id, $checkId, CheckStatus::Waived);

        return new ApplicationCheckResource($this->view($list->execute($actor, $id), $checkId));
    }

    public function cancel(Request $request, int $id, int $checkId, ActorResolver $actors, UpdateCheck $update, ListApplicationChecks $list): ApplicationCheckResource
    {
        $actor = $actors->fromRequest($request);
        $update->close($actor, $id, $checkId, CheckStatus::Cancelled);

        return new ApplicationCheckResource($this->view($list->execute($actor, $id), $checkId));
    }

    public function result(RecordCheckResultRequest $request, int $id, int $checkId, ActorResolver $actors, RecordCheckResult $record, ListApplicationChecks $list): ApplicationCheckResource
    {
        $actor = $actors->fromRequest($request);
        $record->execute($actor, $id, $checkId, $request->outcome(), $request->note(), $request->fileContents(), $request->fileName(), $request->ip(), $request->userAgent());

        return new ApplicationCheckResource($this->view($list->execute($actor, $id), $checkId));
    }

    /** The file attached to a result; every read is audited. */
    public function file(Request $request, int $id, int $checkId, ActorResolver $actors, GetCheckResultFile $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $id, $checkId, $request->ip(), $request->userAgent());

        return PdfDownload::of($file);
    }

    /**
     * @param  list<CheckView>  $views
     */
    private function view(array $views, int $checkId): CheckView
    {
        foreach ($views as $view) {
            if ($view->check->id === $checkId) {
                return $view;
            }
        }

        throw new \LogicException('The check was not in the list.');
    }
}
