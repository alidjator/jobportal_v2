<?php

namespace Src\Recruitment\Infrastructure\JobBoard\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\JobBoard\JobPromotion;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

/**
 * Pembangun baris daftar lowongan (judul, perusahaan, tipe, role, kategori, gaji, flag bookmark)
 * yang dipakai bersama oleh pencarian, home, lamaran, favorit, dan job alert.
 *
 * Membaca tabel `jobs` (Recruitment) serta `companies` & `users` (Company/Identity) secara read-only
 * untuk nama dan logo perusahaan.
 */
final class JobRows
{
    public function __construct(private readonly JobBoardSettings $settings) {}

    /** `jobs` + pemilik perusahaan (companies/users); belum ada filter status. */
    public function base(): Builder
    {
        return DB::table('jobs')
            ->leftJoin('companies', 'companies.id', '=', 'jobs.company_id')
            ->leftJoin('users', 'users.id', '=', 'companies.user_id');
    }

    /** Tambahkan kolom baris daftar. `$candidateId` menentukan flag `bookmarked`. */
    public function select(Builder $query, ?int $candidateId): Builder
    {
        return $query
            ->select([
                'jobs.id', 'jobs.title', 'jobs.slug', 'jobs.country',
                'jobs.featured', 'jobs.featured_until', 'jobs.highlight', 'jobs.highlight_until',
                'jobs.deadline', 'jobs.salary_mode', 'jobs.min_salary', 'jobs.max_salary', 'jobs.custom_salary',
                'jobs.is_remote', 'jobs.status', 'jobs.job_type_id', 'jobs.role_id', 'jobs.category_id',
                'users.name as owner_name', 'companies.logo as company_logo',
            ])
            ->selectRaw('(select count(*) from applied_jobs aj where aj.job_id = jobs.id) as all_applied_jobs_count')
            ->selectRaw(
                '(select count(*) from bookmark_candidate_job bj where bj.job_id = jobs.id and bj.candidate_id = ?) as bookmarked',
                [$candidateId ?? 0],
            );
    }

    /**
     * @param  iterable<object>  $rows  hasil `select()` (boleh ditambah kolom lain, mis. `applied_at`)
     * @return list<array<string, mixed>>
     */
    public function hydrate(iterable $rows): array
    {
        $rows = collect($rows);

        $types = TranslatedNames::for('job_type_translations', 'job_type_id', $rows->pluck('job_type_id')->filter());
        $roles = TranslatedNames::for('job_role_translations', 'job_role_id', $rows->pluck('role_id')->filter());
        $categories = TranslatedNames::for('job_category_translations', 'job_category_id', $rows->pluck('category_id')->filter());

        $featuredDays = $this->settings->featuredDays();
        $highlightDays = $this->settings->highlightDays();

        $result = [];

        foreach ($rows as $row) {
            $item = [
                'id' => (int) $row->id,
                'title' => $row->title,
                'slug' => $row->slug,
                'company_name' => $row->owner_name ?? '',
                'company_logo' => $row->company_logo,
                'job_type' => $types[$row->job_type_id] ?? null,
                'job_role_id' => $row->role_id,
                'job_role' => $roles[$row->role_id] ?? null,
                'category_id' => $row->category_id,
                'category' => $categories[$row->category_id] ?? null,
                'country' => $row->country,
                'is_featured' => JobPromotion::isActive($row->featured, $row->featured_until, $featuredDays),
                'is_highlighted' => JobPromotion::isActive($row->highlight, $row->highlight_until, $highlightDays),
                'is_remote' => $row->is_remote,
                'status' => $row->status,
                'deadline' => $row->deadline,
                'salary_mode' => $row->salary_mode,
                'min_salary' => $row->min_salary,
                'max_salary' => $row->max_salary,
                'custom_salary' => $row->custom_salary,
                'bookmarked' => (int) $row->bookmarked > 0,
                'all_applied_jobs_count' => (int) $row->all_applied_jobs_count,
            ];

            if (property_exists($row, 'applied_at')) {
                $item['applied_at'] = $row->applied_at;
            }

            $result[] = $item;
        }

        return $result;
    }
}
