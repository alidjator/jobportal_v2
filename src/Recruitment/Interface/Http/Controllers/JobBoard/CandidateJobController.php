<?php

namespace Src\Recruitment\Interface\Http\Controllers\JobBoard;

use Illuminate\Http\Request;
use Src\Recruitment\Application\JobBoard\Commands\ApplyToJob;
use Src\Recruitment\Application\JobBoard\Commands\ToggleJobBookmark;
use Src\Recruitment\Application\JobBoard\Queries\GetJobDetail;
use Src\Recruitment\Application\JobBoard\Queries\ListAppliedJobs;
use Src\Recruitment\Application\JobBoard\Queries\ListFavoriteJobs;
use Src\Recruitment\Application\JobBoard\Queries\ListJobAlerts;
use Src\Recruitment\Application\JobBoard\Queries\SearchJobs;
use Src\Recruitment\Interface\Http\Requests\JobBoard\ApplyJobRequest;
use Src\Recruitment\Interface\Http\Requests\JobBoard\SearchJobsRequest;
use Src\Recruitment\Interface\Http\Resources\JobBoard\JobPresenter;
use Src\Recruitment\Interface\Http\Resources\JobBoard\Paginated;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

/** Endpoint kandidat (`auth:sanctum`). */
class CandidateJobController extends ApiController
{
    /** Aplikasi lama hanya mengembalikan daftar item (tanpa meta paginasi), 12 per halaman. */
    public function index(SearchJobsRequest $request, SearchJobs $query, JobPresenter $presenter)
    {
        $page = $query($request->filters(), $request->user()->getKey(), 12, $request->pageNumber());

        return ApiResponse::success(array_map($presenter->listItem(...), $page->items));
    }

    public function show(string $slug, Request $request, GetJobDetail $query, JobPresenter $presenter)
    {
        $user = $request->user();
        $data = $query($slug, $user->getKey(), $user->role);

        return ApiResponse::success([
            'job' => $presenter->detail($data['job']),
            'related_jobs' => array_map($presenter->listItem(...), $data['related_jobs']),
            'resumes' => $data['resumes'],
        ]);
    }

    public function applied(SearchJobsRequest $request, ListAppliedJobs $query, JobPresenter $presenter)
    {
        $page = $query($request->user()->getKey(), $request->perPage(12), $request->pageNumber());

        return ApiResponse::success(Paginated::paginator($page->map($presenter->appliedItem(...)), withQueryString: true));
    }

    public function favorites(SearchJobsRequest $request, ListFavoriteJobs $query, JobPresenter $presenter)
    {
        $page = $query($request->user()->getKey(), 8, $request->pageNumber());

        return ApiResponse::success(Paginated::resourceShape($page->map($presenter->listItem(...))));
    }

    public function bookmark(int $job, Request $request, ToggleJobBookmark $command)
    {
        $attached = $command($request->user()->getKey(), $job);

        return ApiResponse::success([
            'message' => $attached ? 'Job added to favorite list' : 'Job removed from favorite list',
            'status' => $attached,
        ]);
    }

    public function apply(ApplyJobRequest $request, ApplyToJob $command)
    {
        $command(
            $request->user()->getKey(),
            (int) $request->input('job_id'),
            (string) $request->input('resume_id'),
            (string) $request->input('cover_letter'),
        );

        return ApiResponse::success([
            'message' => 'Your job application submitted successfully!',
            'status' => true,
        ]);
    }

    public function alerts(SearchJobsRequest $request, ListJobAlerts $query, JobPresenter $presenter)
    {
        $page = $query($request->user()->getKey(), $request->perPage(8), $request->pageNumber());

        return ApiResponse::success([
            'notifications' => Paginated::paginator($page->map(fn (array $row) => $presenter->alertItem($row['job']))),
            'message' => 'Job alert retried successful!',
        ]);
    }
}
