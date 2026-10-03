<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Company\Application\Queries\GetCompanyDetails;
use Src\Company\Application\Queries\ListCompanies;
use Src\Company\Interface\Http\Requests\ListCompaniesRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

/** Daftar & detail perusahaan (publik). */
class CompanyDirectoryController extends ApiController
{
    public function index(ListCompaniesRequest $request, ListCompanies $list): JsonResponse
    {
        $viewer = $request->user('sanctum');

        // Perilaku lama: akun perusahaan tidak boleh melihat daftar perusahaan.
        if ($viewer !== null && $viewer->role === 'company') {
            return ApiResponse::error('Unauthorized access');
        }

        $companies = $list(
            $request->only(['keyword', 'industry_type', 'organization_type', 'location', 'lat', 'long', 'sortBy']),
            $request->filled('paginate') ? $request->integer('paginate') : 12,
            $this->selectedCountry($request),
            $viewer ? (int) $viewer->getAuthIdentifier() : null,
        );

        return ApiResponse::success($companies);
    }

    public function show(Request $request, string $username, GetCompanyDetails $details): JsonResponse
    {
        $viewer = $request->user('sanctum');

        return ApiResponse::success($details(
            $username,
            $this->selectedCountry($request),
            $viewer ? (int) $viewer->getAuthIdentifier() : null,
        ));
    }

    private function selectedCountry(Request $request): ?string
    {
        return $request->header('selected_country') ?? $request->header('X-Country');
    }
}
