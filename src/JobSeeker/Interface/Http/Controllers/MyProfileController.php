<?php

namespace Src\JobSeeker\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\JobSeeker\Application\AddMyEducation;
use Src\JobSeeker\Application\AddMyExperience;
use Src\JobSeeker\Application\ChangeMyEducation;
use Src\JobSeeker\Application\ChangeMyExperience;
use Src\JobSeeker\Application\DeleteMyEducation;
use Src\JobSeeker\Application\DeleteMyExperience;
use Src\JobSeeker\Application\DeleteMyResume;
use Src\JobSeeker\Application\GetMyProfile;
use Src\JobSeeker\Application\GetMyResumeFile;
use Src\JobSeeker\Application\ListMyEducations;
use Src\JobSeeker\Application\ListMyExperiences;
use Src\JobSeeker\Application\ListMyResumes;
use Src\JobSeeker\Application\RenameMyResume;
use Src\JobSeeker\Application\ReplaceMyLanguages;
use Src\JobSeeker\Application\ReplaceMyLinks;
use Src\JobSeeker\Application\ReplaceMySkills;
use Src\JobSeeker\Application\UpdateMyProfile;
use Src\JobSeeker\Application\UploadMyResume;
use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\WorkExperience;
use Src\JobSeeker\Interface\Http\Requests\EducationRequest;
use Src\JobSeeker\Interface\Http\Requests\ExperienceRequest;
use Src\JobSeeker\Interface\Http\Requests\RenameMyResumeRequest;
use Src\JobSeeker\Interface\Http\Requests\ReplaceMyLanguagesRequest;
use Src\JobSeeker\Interface\Http\Requests\ReplaceMyLinksRequest;
use Src\JobSeeker\Interface\Http\Requests\ReplaceMySkillsRequest;
use Src\JobSeeker\Interface\Http\Requests\UpdateMyProfileRequest;
use Src\JobSeeker\Interface\Http\Requests\UploadMyResumeRequest;
use Src\JobSeeker\Interface\Http\Resources\EducationResource;
use Src\JobSeeker\Interface\Http\Resources\ExperienceResource;
use Src\JobSeeker\Interface\Http\Resources\MyProfileResource;
use Src\JobSeeker\Interface\Http\Resources\ResumeResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The job seeker's own profile and its lists. Whatever changes the profile answers with the whole profile, so the client has the new completeness at once;
 * the experiences and the education entries answer with the entry.
 */
final class MyProfileController extends ApiController
{
    public function show(Request $request, RequestContext $context, ActorResolver $actors, GetMyProfile $get): MyProfileResource
    {
        return new MyProfileResource($get->execute($actors->fromRequest($request), $context->language));
    }

    public function update(UpdateMyProfileRequest $request, RequestContext $context, ActorResolver $actors, UpdateMyProfile $update, GetMyProfile $get): MyProfileResource
    {
        $actor = $actors->fromRequest($request);
        $update->execute($actor, $request->changes());

        return new MyProfileResource($get->execute($actor, $context->language));
    }

    public function replaceSkills(ReplaceMySkillsRequest $request, RequestContext $context, ActorResolver $actors, ReplaceMySkills $replace, GetMyProfile $get): MyProfileResource
    {
        $actor = $actors->fromRequest($request);
        $replace->execute($actor, $request->skillIds());

        return new MyProfileResource($get->execute($actor, $context->language));
    }

    public function replaceLanguages(ReplaceMyLanguagesRequest $request, RequestContext $context, ActorResolver $actors, ReplaceMyLanguages $replace, GetMyProfile $get): MyProfileResource
    {
        $actor = $actors->fromRequest($request);
        $replace->execute($actor, $request->languages());

        return new MyProfileResource($get->execute($actor, $context->language));
    }

    public function replaceLinks(ReplaceMyLinksRequest $request, RequestContext $context, ActorResolver $actors, ReplaceMyLinks $replace, GetMyProfile $get): MyProfileResource
    {
        $actor = $actors->fromRequest($request);
        $replace->execute($actor, $request->links());

        return new MyProfileResource($get->execute($actor, $context->language));
    }

    public function experiences(Request $request, ActorResolver $actors, ListMyExperiences $list): AnonymousResourceCollection
    {
        return ExperienceResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function addExperience(ExperienceRequest $request, ActorResolver $actors, AddMyExperience $add): JsonResponse
    {
        $changes = $request->changes();
        $draft = new WorkExperience(
            0, $changes['jobTitle'], $changes['companyName'], $changes['jobTypeId'] ?? null, $changes['locationText'] ?? null, $changes['startDate'],
            $changes['endDate'] ?? null, $changes['isCurrent'] ?? false, $changes['description'] ?? null,
        );

        return (new ExperienceResource($add->execute($actors->fromRequest($request), $draft)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function changeExperience(ExperienceRequest $request, int $id, ActorResolver $actors, ChangeMyExperience $change): ExperienceResource
    {
        return new ExperienceResource($change->execute($actors->fromRequest($request), $id, $request->changes()));
    }

    public function deleteExperience(Request $request, int $id, ActorResolver $actors, DeleteMyExperience $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function educations(Request $request, ActorResolver $actors, ListMyEducations $list): AnonymousResourceCollection
    {
        return EducationResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function addEducation(EducationRequest $request, ActorResolver $actors, AddMyEducation $add): JsonResponse
    {
        $changes = $request->changes();
        $draft = new Education(
            0, $changes['educationLevelId'], $changes['institution'], $changes['fieldOfStudy'] ?? null, $changes['startYear'], $changes['endYear'] ?? null,
            $changes['isCurrent'] ?? false, $changes['gpa'] ?? null, $changes['description'] ?? null,
        );

        return (new EducationResource($add->execute($actors->fromRequest($request), $draft)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function changeEducation(EducationRequest $request, int $id, ActorResolver $actors, ChangeMyEducation $change): EducationResource
    {
        return new EducationResource($change->execute($actors->fromRequest($request), $id, $request->changes()));
    }

    public function deleteEducation(Request $request, int $id, ActorResolver $actors, DeleteMyEducation $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function resumes(Request $request, ActorResolver $actors, ListMyResumes $list): AnonymousResourceCollection
    {
        return ResumeResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function uploadResume(UploadMyResumeRequest $request, ActorResolver $actors, UploadMyResume $upload): JsonResponse
    {
        $resume = $upload->execute($actors->fromRequest($request), $request->contents(), $request->originalName());

        return (new ResumeResource($resume))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function renameResume(RenameMyResumeRequest $request, int $id, ActorResolver $actors, RenameMyResume $rename): ResumeResource
    {
        return new ResumeResource($rename->execute($actors->fromRequest($request), $id, $request->name()));
    }

    public function deleteResume(Request $request, int $id, ActorResolver $actors, DeleteMyResume $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    /** The download is named after the owner's display name, with `.pdf` added; the plain ASCII fallback is for clients that cannot read the encoded name. */
    public function downloadResume(Request $request, int $id, ActorResolver $actors, GetMyResumeFile $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $id);
        $name = str_ends_with(strtolower($file->name), '.pdf') ? $file->name : $file->name.'.pdf';
        $fallback = str_replace(['%', '/', '\\', '"'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $name));

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
