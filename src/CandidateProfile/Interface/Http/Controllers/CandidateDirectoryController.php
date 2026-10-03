<?php

namespace Src\CandidateProfile\Interface\Http\Controllers;

use Src\CandidateProfile\Application\Queries\GetCandidateDetail;
use Src\CandidateProfile\Application\Queries\ListCandidates;
use Src\CandidateProfile\Interface\Http\Requests\ListCandidatesRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

/** Daftar & detail kandidat publik (token Sanctum opsional). */
class CandidateDirectoryController extends ApiController
{
    public function index(ListCandidatesRequest $request, ListCandidates $query)
    {
        $user = $request->user('sanctum');

        // Kandidat tidak boleh melihat daftar kandidat lain (perilaku aplikasi lama).
        if ($user?->role === 'candidate') {
            return ApiResponse::unauthenticated('Unauthorized Access');
        }

        $filters = $request->only([
            'keyword', 'status', 'profession', 'experience', 'education', 'gender',
            'language', 'skills', 'location', 'lat', 'long', 'sortby',
        ]);

        return ApiResponse::success($query(
            $filters,
            $request->integer('paginate', 8),
            $user?->role === 'company' ? $user->id : null,
        ));
    }

    public function show(string $username, GetCandidateDetail $query)
    {
        return ApiResponse::success($query($username));
    }
}
