<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Company\Application\Commands\CreateApplicationGroup;
use Src\Company\Application\Commands\CreateBookmarkCategory;
use Src\Company\Application\Commands\DeleteApplicationGroup;
use Src\Company\Application\Commands\DeleteBookmarkCategory;
use Src\Company\Application\Commands\SubmitAccountProgress;
use Src\Company\Application\Commands\ToggleCandidateBookmark;
use Src\Company\Application\Commands\UpdateApplicationGroup;
use Src\Company\Application\Commands\UpdateBookmarkCategory;
use Src\Company\Application\Queries\GetAccountProgress;
use Src\Company\Application\Queries\GetBookmarkCategory;
use Src\Company\Application\Queries\GetCompanyDashboard;
use Src\Company\Application\Queries\GetCompanyProfile;
use Src\Company\Application\Queries\GetSocialLinks;
use Src\Company\Application\Queries\ListApplicationGroups;
use Src\Company\Application\Queries\ListBookmarkCategories;
use Src\Company\Application\Queries\ListBookmarkedCandidates;
use Src\Company\Interface\Http\Requests\AccountProgressRequest;
use Src\Company\Interface\Http\Requests\ApplicationGroupRequest;
use Src\Company\Interface\Http\Requests\BookmarkCandidateRequest;
use Src\Company\Interface\Http\Requests\BookmarkCategoryRequest;
use Src\Company\Interface\Http\Requests\ListBookmarkedCandidatesRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

class CompanyController extends ApiController
{
    public function company(Request $request, GetCompanyProfile $profile): JsonResponse
    {
        return ApiResponse::success($profile($this->userId($request)));
    }

    public function dashboard(Request $request, GetCompanyDashboard $dashboard): JsonResponse
    {
        return ApiResponse::success($dashboard($this->userId($request)));
    }

    public function bookmarkCandidate(BookmarkCandidateRequest $request, ToggleCandidateBookmark $toggle): JsonResponse
    {
        $user = $request->user();
        $message = $toggle(
            ['id' => (int) $user->getAuthIdentifier(), 'name' => (string) $user->name, 'username' => (string) $user->username],
            $request->string('username')->toString(),
            $request->integer('category_id') ?: null,
        );

        return ApiResponse::success(['success' => $message]);
    }

    public function bookmarkedCandidates(ListBookmarkedCandidatesRequest $request, ListBookmarkedCandidates $list): JsonResponse
    {
        return ApiResponse::success($list($this->userId($request), $request->categoryId()));
    }

    public function bookmarkCategories(Request $request, ListBookmarkCategories $list): JsonResponse
    {
        return ApiResponse::success($list($this->userId($request)));
    }

    public function storeBookmarkCategory(BookmarkCategoryRequest $request, CreateBookmarkCategory $create): JsonResponse
    {
        return ApiResponse::success($create($this->userId($request), $request->string('name')->toString()));
    }

    public function editBookmarkCategory(Request $request, int $category, GetBookmarkCategory $get): JsonResponse
    {
        return ApiResponse::success($get($this->userId($request), $category));
    }

    public function updateBookmarkCategory(BookmarkCategoryRequest $request, int $category, UpdateBookmarkCategory $update): JsonResponse
    {
        return ApiResponse::success($update($this->userId($request), $category, $request->string('name')->toString()));
    }

    public function deleteBookmarkCategory(Request $request, int $category, DeleteBookmarkCategory $delete): JsonResponse
    {
        // Aplikasi lama membalas `{success: "..."}` (respondOk) tanpa pembungkus `data`.
        return ApiResponse::raw(['success' => $delete($this->userId($request), $category)]);
    }

    public function applicationGroups(Request $request, ListApplicationGroups $list): JsonResponse
    {
        return ApiResponse::success($list($this->userId($request)));
    }

    public function storeApplicationGroup(ApplicationGroupRequest $request, CreateApplicationGroup $create): JsonResponse
    {
        return ApiResponse::success($create($this->userId($request), $request->string('name')->toString()));
    }

    public function updateApplicationGroup(ApplicationGroupRequest $request, int $group, UpdateApplicationGroup $update): JsonResponse
    {
        return ApiResponse::success($update($this->userId($request), $group, $request->string('name')->toString()));
    }

    public function deleteApplicationGroup(Request $request, int $group, DeleteApplicationGroup $delete): JsonResponse
    {
        return ApiResponse::success(['success' => $delete($this->userId($request), $group)]);
    }

    public function accountProgress(Request $request, GetAccountProgress $progress): JsonResponse
    {
        return ApiResponse::success($progress($this->userId($request)));
    }

    public function submitAccountProgress(AccountProgressRequest $request, SubmitAccountProgress $submit): JsonResponse
    {
        $userId = $this->userId($request);

        $result = match ($request->step()) {
            'company' => $submit->company(
                $userId,
                $request->filled('name') ? $request->string('name')->toString() : null,
                $request->string('bio')->toString(),
                $request->file('image'),
                $request->file('banner'),
            ),
            'founding' => ['success' => $submit->founding($userId, [
                'organization_type' => (string) $request->input('organization_type_id'),
                'industry_type' => (string) $request->input('industry_type_id'),
                'establishment_date' => $request->input('establishment_date') ?: null,
                'team_size_id' => $request->filled('team_size_id') ? $request->integer('team_size_id') : null,
                'website' => $request->input('website') ?: null,
                'vision' => $request->string('vision')->toString(),
            ])],
            'social' => ['success' => $submit->social($userId, $request->socialLinks())],
            default => ['success' => $submit->contact(
                $userId,
                array_filter($request->only(['phone', 'email', 'secondary_phone', 'secondary_email']), fn ($v) => $v !== null),
                array_filter($request->only([
                    'address', 'neighborhood', 'locality', 'place', 'district', 'subdistrict',
                    'kelurahan', 'postcode', 'region', 'country', 'long', 'lat',
                ]), fn ($v) => $v !== null),
            )],
        };

        return ApiResponse::success($result);
    }

    public function socialLinks(Request $request, GetSocialLinks $links): JsonResponse
    {
        return ApiResponse::success($links($this->userId($request)));
    }

    private function userId(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }
}
