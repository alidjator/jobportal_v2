<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Src\Company\Domain\BookmarkRepository;
use Src\Company\Infrastructure\Persistence\Eloquent\BookmarkCategoryRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\CompanyRecord;

class EloquentBookmarkRepository implements BookmarkRepository
{
    public function categories(int $companyId): array
    {
        return BookmarkCategoryRecord::query()->where('company_id', $companyId)->orderBy('id')->get()->toArray();
    }

    public function category(int $companyId, int $categoryId): ?array
    {
        return BookmarkCategoryRecord::query()->where('company_id', $companyId)->whereKey($categoryId)->first()?->toArray();
    }

    public function createCategory(int $companyId, string $name): array
    {
        return BookmarkCategoryRecord::query()->create(['company_id' => $companyId, 'name' => $name])->toArray();
    }

    public function updateCategory(int $categoryId, string $name): array
    {
        $category = BookmarkCategoryRecord::query()->findOrFail($categoryId);
        $category->update(['name' => $name]);

        return $category->toArray();
    }

    public function deleteCategory(int $categoryId): void
    {
        BookmarkCategoryRecord::query()->whereKey($categoryId)->delete();
    }

    public function categoryExists(int $companyId, int $categoryId): bool
    {
        return BookmarkCategoryRecord::query()->where('company_id', $companyId)->whereKey($categoryId)->exists();
    }

    public function paginateCandidates(int $companyId, ?int $categoryId, int $perPage): LengthAwarePaginator
    {
        $query = CompanyRecord::query()->findOrFail($companyId)->bookmarkCandidates();

        if ($categoryId !== null) {
            $query->wherePivot('category_id', $categoryId);
        }

        return $query->with('profession')->orderByPivot('id')->paginate($perPage)->withQueryString();
    }

    public function findCandidateByUsername(string $username): ?array
    {
        // Tabel `users` + `candidates` (Identity / CandidateProfile), read-only: mencari kandidat untuk di-bookmark.
        $row = DB::table('users')
            ->join('candidates', 'candidates.user_id', '=', 'users.id')
            ->where('users.username', $username)
            ->first(['candidates.id as candidate_id', 'users.name', 'users.username', 'users.shortlisted_alert']);

        return $row === null ? null : [
            'id' => (int) $row->candidate_id,
            'name' => $row->name,
            'username' => $row->username,
            'shortlisted_alert' => (bool) $row->shortlisted_alert,
        ];
    }

    public function isBookmarked(int $companyId, int $candidateId): bool
    {
        return DB::table('bookmark_company')->where('company_id', $companyId)->where('candidate_id', $candidateId)->exists();
    }

    public function addBookmark(int $companyId, int $candidateId, ?int $categoryId): void
    {
        DB::table('bookmark_company')->insert([
            'company_id' => $companyId, 'candidate_id' => $candidateId, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function removeBookmark(int $companyId, int $candidateId): void
    {
        DB::table('bookmark_company')->where('company_id', $companyId)->where('candidate_id', $candidateId)->delete();
    }

    public function remainingCvViews(int $companyId): ?int
    {
        // Tabel `user_plans` (Billing): kuota lihat CV kandidat milik perusahaan.
        $plan = DB::table('user_plans')->where('company_id', $companyId)->first(['candidate_cv_view_limit', 'candidate_cv_view_limitation']);

        if ($plan === null || $plan->candidate_cv_view_limitation === 'unlimited') {
            return null;
        }

        return (int) $plan->candidate_cv_view_limit;
    }

    public function consumeCvView(int $companyId): void
    {
        // Tulis terbatas pada `user_plans` (Billing): mengurangi kuota lihat CV, seperti aplikasi lama.
        DB::table('user_plans')->where('company_id', $companyId)->where('candidate_cv_view_limit', '>', 0)->decrement('candidate_cv_view_limit');
    }
}
