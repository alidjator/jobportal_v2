<?php

namespace Src\Recruitment\Interface\Http\Controllers\JobBoard;

use Illuminate\Http\Request;
use Src\Recruitment\Application\JobBoard\Queries\GetHome;
use Src\Recruitment\Application\JobBoard\Queries\GetJobDetail;
use Src\Recruitment\Application\JobBoard\Queries\SearchJobs;
use Src\Recruitment\Interface\Http\Requests\JobBoard\SearchJobsRequest;
use Src\Recruitment\Interface\Http\Resources\JobBoard\JobPresenter;
use Src\Recruitment\Interface\Http\Resources\JobBoard\Paginated;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

/** Endpoint publik (token Sanctum opsional untuk flag bookmark/applied). */
class PublicJobController extends ApiController
{
    public function home(Request $request, GetHome $query, JobPresenter $presenter)
    {
        return ApiResponse::success($presenter->home($query($request->user('sanctum')?->getKey())));
    }

    public function index(SearchJobsRequest $request, SearchJobs $query, JobPresenter $presenter)
    {
        $page = $query($request->filters(), $request->user('sanctum')?->getKey(), $request->perPage(12), $request->pageNumber());

        return ApiResponse::success(Paginated::paginator($page->map($presenter->searchItem(...)), onEachSideZero: true, withQueryString: true));
    }

    public function show(string $slug, Request $request, GetJobDetail $query, JobPresenter $presenter)
    {
        $user = $request->user('sanctum');
        $data = $query($slug, $user?->getKey(), $user?->role);

        return ApiResponse::success([
            'job' => $presenter->detail($data['job']),
            'related_jobs' => array_map($presenter->listItem(...), $data['related_jobs']),
            'resumes' => $data['resumes'],
        ]);
    }
}
