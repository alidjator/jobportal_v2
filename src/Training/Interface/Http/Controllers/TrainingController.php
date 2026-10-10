<?php

namespace Src\Training\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Src\Training\Application\ListCourses;
use Src\Training\Application\ListMyCourses;
use Src\Training\Interface\Http\Requests\ListCoursesRequest;
use Src\Training\Interface\Http\Resources\CourseResource;
use Src\Training\Interface\Http\Resources\EnrolledCourseResource;

/** The courses of the training platform: the public catalog, and the courses of the signed-in job seeker. */
final class TrainingController extends ApiController
{
    public function courses(ListCoursesRequest $request, ListCourses $list): AnonymousResourceCollection
    {
        return PaginatedResources::of($list->execute($request->search(), $request->pageRequest()), CourseResource::class, $request);
    }

    public function mine(Request $request, ActorResolver $actors, ListMyCourses $list): AnonymousResourceCollection
    {
        return EnrolledCourseResource::collection($list->execute($actors->fromRequest($request)));
    }
}
