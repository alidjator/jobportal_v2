<?php

namespace Src\Recruitment\Interface\Http\Controllers\JobManagement;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Recruitment\Application\JobManagement\Commands\ChangeJobStatus;
use Src\Recruitment\Application\JobManagement\Commands\CloneJob;
use Src\Recruitment\Application\JobManagement\Commands\MoveApplicationToGroup;
use Src\Recruitment\Application\JobManagement\Commands\PromoteJob;
use Src\Recruitment\Application\JobManagement\Commands\StoreJob;
use Src\Recruitment\Application\JobManagement\Commands\UpdateJob;
use Src\Recruitment\Application\JobManagement\Queries\GetCreateJobData;
use Src\Recruitment\Application\JobManagement\Queries\GetEditJobData;
use Src\Recruitment\Application\JobManagement\Queries\GetResumeFile;
use Src\Recruitment\Application\JobManagement\Queries\ListCompanyJobs;
use Src\Recruitment\Application\JobManagement\Queries\ListJobApplications;
use Src\Recruitment\Domain\JobManagement\JobStatus;
use Src\Recruitment\Domain\JobManagement\PromotionBadge;
use Src\Recruitment\Interface\Http\Requests\JobManagement\ApplicationGroupUpdateRequest;
use Src\Recruitment\Interface\Http\Requests\JobManagement\ChangeJobStatusRequest;
use Src\Recruitment\Interface\Http\Requests\JobManagement\CloneJobRequest;
use Src\Recruitment\Interface\Http\Requests\JobManagement\PromoteJobRequest;
use Src\Recruitment\Interface\Http\Requests\JobManagement\StoreJobRequest;
use Src\Recruitment\Interface\Http\Requests\JobManagement\UpdateJobRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

/**
 * Sisi perusahaan: kelola lowongan & lamaran.
 *
 * Catatan bentuk respons: aplikasi lama membungkus hasil service (yang sendiri sudah `{data: ...}`)
 * lagi di controller. Isi `data` di sini mengikuti bentuk maksud aslinya: untuk create/store/edit/
 * update/clone `{data: {data: ...}}`, untuk promote/change-status `{data: {success}}`.
 */
class CompanyJobController extends ApiController
{
    public function create(GetCreateJobData $query): JsonResponse
    {
        return ApiResponse::success(['data' => $query()]);
    }

    public function store(StoreJobRequest $request, StoreJob $useCase): JsonResponse
    {
        return ApiResponse::success(['data' => $useCase($this->userId($request), $request->toPosting())]);
    }

    public function edit(Request $request, string $slug, GetEditJobData $query): JsonResponse
    {
        return ApiResponse::success(['data' => $query($this->userId($request), $slug)]);
    }

    public function update(UpdateJobRequest $request, string $slug, UpdateJob $useCase): JsonResponse
    {
        return ApiResponse::success(['data' => $useCase($this->userId($request), $slug, $request->toPosting())]);
    }

    public function promote(PromoteJobRequest $request, PromoteJob $useCase): JsonResponse
    {
        $message = $useCase(
            $this->userId($request),
            (string) $request->validated('slug'),
            PromotionBadge::from((string) $request->validated('badge')),
        );

        return ApiResponse::success(['success' => $message]);
    }

    public function replicate(CloneJobRequest $request, CloneJob $useCase): JsonResponse
    {
        return ApiResponse::success(['data' => $useCase($this->userId($request), (string) $request->validated('slug'))]);
    }

    public function changeStatus(ChangeJobStatusRequest $request, ChangeJobStatus $useCase): JsonResponse
    {
        $message = $useCase(
            $this->userId($request),
            (string) $request->validated('slug'),
            JobStatus::from((string) $request->validated('status')),
        );

        return ApiResponse::success(['success' => $message]);
    }

    public function index(Request $request, ListCompanyJobs $query): JsonResponse
    {
        $status = $request->query('status');

        return ApiResponse::success($query($this->userId($request), is_string($status) ? $status : null));
    }

    public function applications(Request $request, int $id, ListJobApplications $query): JsonResponse
    {
        return ApiResponse::success($query($this->userId($request), $id));
    }

    public function groupUpdate(ApplicationGroupUpdateRequest $request, int $id, MoveApplicationToGroup $useCase): JsonResponse
    {
        $useCase($this->userId($request), $id, (int) $request->validated('group'));

        return ApiResponse::success(['message' => 'Application updated successful!']);
    }

    /** Mengembalikan URL file CV sebagai string biasa (bukan JSON), seperti aplikasi lama. */
    public function downloadCv(Request $request, int $id, GetResumeFile $query): Response
    {
        return response(asset($query($this->userId($request), $id)));
    }

    private function userId(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }
}
