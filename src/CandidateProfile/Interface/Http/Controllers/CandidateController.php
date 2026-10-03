<?php

namespace Src\CandidateProfile\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\CandidateProfile\Application\Commands\ChangePassword;
use Src\CandidateProfile\Application\Commands\DeleteAccount;
use Src\CandidateProfile\Application\Commands\DeleteResume;
use Src\CandidateProfile\Application\Commands\UpdateContactSettings;
use Src\CandidateProfile\Application\Commands\UpdatePersonalSettings;
use Src\CandidateProfile\Application\Commands\UpdateProfileSettings;
use Src\CandidateProfile\Application\Commands\UpdateResume;
use Src\CandidateProfile\Application\Commands\UpdateSocialSettings;
use Src\CandidateProfile\Application\Commands\UploadResume;
use Src\CandidateProfile\Application\Queries\GetCandidateDashboard;
use Src\CandidateProfile\Application\Queries\GetCandidateProfile;
use Src\CandidateProfile\Application\Queries\GetCandidateSettings;
use Src\CandidateProfile\Application\Queries\GetResume;
use Src\CandidateProfile\Application\Queries\ListResumes;
use Src\CandidateProfile\Interface\Http\Requests\FetchCandidateSettingsRequest;
use Src\CandidateProfile\Interface\Http\Requests\UpdateCandidateSettingsRequest;
use Src\CandidateProfile\Interface\Http\Requests\UpdateResumeRequest;
use Src\CandidateProfile\Interface\Http\Requests\UploadResumeRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

class CandidateController extends ApiController
{
    public function candidate(Request $request, GetCandidateProfile $query)
    {
        return ApiResponse::success($query($request->user()->id));
    }

    public function dashboard(Request $request, GetCandidateDashboard $query)
    {
        return ApiResponse::success($query($request->user()->id));
    }

    public function fetchSettings(FetchCandidateSettingsRequest $request, GetCandidateSettings $query)
    {
        return ApiResponse::success($query($request->user()->id, $request->string('type')->toString()));
    }

    public function updateSettings(UpdateCandidateSettingsRequest $request)
    {
        $userId = $request->user()->id;
        $input = $request->validated();

        $data = match ($request->type()) {
            'personal' => app(UpdatePersonalSettings::class)($userId, $input, $request->file('image')),
            'profile' => app(UpdateProfileSettings::class)($userId, $input),
            'social' => app(UpdateSocialSettings::class)($userId, $input['social_media'] ?? [], $input['url'] ?? []),
            'contact' => app(UpdateContactSettings::class)($userId, $input),
            'password' => app(ChangePassword::class)($userId, $input['current_password'], $input['password']),
            'account-delete' => app(DeleteAccount::class)($userId, $input['current_password']),
            default => throw new BusinessRuleViolation('Invalid settings type'),
        };

        return ApiResponse::success($data);
    }

    /** Bukan kandidat => daftar kosong (seperti aplikasi lama). */
    public function getResumes(Request $request, ListResumes $query)
    {
        if ($request->user()->role !== 'candidate') {
            return ApiResponse::success([]);
        }

        return ApiResponse::success($query($request->user()->id));
    }

    public function getResumeById(Request $request, int $id, GetResume $query)
    {
        return ApiResponse::success($query($request->user()->id, $id));
    }

    public function uploadResume(UploadResumeRequest $request, UploadResume $command)
    {
        return ApiResponse::success($command($request->user()->id, $request->validated('name'), $request->file('resume_file')));
    }

    public function updateResume(UpdateResumeRequest $request, int $id, UpdateResume $command)
    {
        return ApiResponse::success($command($request->user()->id, $id, $request->validated('name'), $request->file('resume_file')));
    }

    public function deleteResume(Request $request, int $id, DeleteResume $command)
    {
        return ApiResponse::success($command($request->user()->id, $id));
    }
}
