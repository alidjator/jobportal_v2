<?php

namespace Src\Recruitment\Interface\Http\Resources\JobBoard;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Src\Recruitment\Domain\JobBoard\SalaryFormatter;

/**
 * Bentuk JSON lowongan seperti `JobListResource`, `JobDetailResource`, `Home/*` dan
 * respons service API aplikasi lama.
 */
final class JobPresenter
{
    private const DEFAULT_IMAGE = 'backend/image/default.png';

    public function __construct(private readonly SalaryFormatter $salary) {}

    /** @param  array<string, mixed>  $r */
    public function listItem(array $r): array
    {
        return [
            'title' => $r['title'],
            'slug' => $r['slug'],
            'job_details' => $this->jobUrl($r['slug']),
            'company_name' => $r['company_name'],
            'company_logo' => $this->assetUrl($r['company_logo']),
            'job_type' => $r['job_type'],
            'job_role' => $r['job_role_id'],
            'category' => $r['category_id'],
            'country' => $r['country'],
            'is_featured' => $r['is_featured'],
            'is_highlighted' => $r['is_highlighted'],
            'deadline' => $r['deadline'],
            'salary' => $this->salaryOf($r),
            'salary_mode' => $r['salary_mode'],
            'min_salary' => $r['min_salary'],
            'max_salary' => $r['max_salary'],
            'bookmarked' => $r['bookmarked'],
            'allAppliedJobs_count' => $r['all_applied_jobs_count'],
        ];
    }

    /** @param  array<string, mixed>  $r */
    public function searchItem(array $r): array
    {
        return [
            'id' => $r['id'],
            'title' => $r['title'],
            'slug' => $r['slug'],
            'job_details' => $this->jobUrl($r['slug']),
            'company_name' => $r['company_name'],
            'company_logo' => $this->assetUrl($r['company_logo']),
            'job_type' => $r['job_type'],
            'job_role' => $r['job_role'],
            'category' => $r['category'],
            'country' => $r['country'],
            'is_featured' => $r['is_featured'],
            'is_highlighted' => $r['is_highlighted'],
            'deadline' => $r['deadline'],
            'salary' => $this->salaryOf($r),
            'salary_mode' => $r['salary_mode'],
            'min_salary' => $r['min_salary'],
            'max_salary' => $r['max_salary'],
            'bookmarked' => $r['bookmarked'],
            'allAppliedJobs_count' => $r['all_applied_jobs_count'],
        ];
    }

    /** @param  array<string, mixed>  $r */
    public function appliedItem(array $r): array
    {
        $appliedAt = Carbon::parse($r['applied_at']);

        return [
            'title' => $r['title'],
            'slug' => $r['slug'],
            'job_details' => $this->jobUrl($r['slug']),
            'company_name' => $r['company_name'],
            'company_logo' => $this->assetUrl($r['company_logo']),
            'job_type' => $r['job_type'],
            'job_role' => $r['job_role'],
            'country' => $r['country'],
            'deadline' => $r['deadline'],
            'salary' => $this->salaryOf($r),
            'salary_mode' => $r['salary_mode'],
            'min_salary' => $r['min_salary'],
            'max_salary' => $r['max_salary'],
            'is_featured' => $r['is_featured'],
            'is_highlighted' => $r['is_highlighted'],
            'is_remote' => $r['is_remote'],
            'status' => $r['status'],
            'applied_human_time' => $appliedAt->diffForHumans(),
            'applied_at' => $appliedAt->format('d M Y h:i A'),
        ];
    }

    /** @param  array<string, mixed>  $r */
    public function featuredItem(array $r): array
    {
        return [
            'title' => $r['title'],
            'slug' => $r['slug'],
            'job_details' => $this->jobUrl($r['slug']),
            'company_name' => $r['company_name'],
            'company_logo' => $this->assetUrl($r['company_logo']),
            'job_type' => $r['job_type'],
            'country' => $r['country'],
            'is_featured' => $r['is_featured'],
            'is_highlighted' => $r['is_highlighted'],
            'salary' => $this->salaryOf($r),
            'salary_mode' => $r['salary_mode'],
            'min_salary' => $r['min_salary'],
            'max_salary' => $r['max_salary'],
            'bookmarked' => $r['bookmarked'],
        ];
    }

    /** @param  array<string, mixed>|null  $r */
    public function alertItem(?array $r): ?array
    {
        if ($r === null) {
            return null;
        }

        return [
            'title' => $r['title'],
            'slug' => $r['slug'],
            'job_type' => $r['job_type'],
            'salary' => $this->salaryOf($r),
            'deadline' => $r['deadline'],
            'country' => $r['country'],
            'company_name' => $r['company_name'],
            'company_logo' => $this->assetUrl($r['company_logo']),
        ];
    }

    /** @param  array<string, mixed>  $j */
    public function detail(array $j): array
    {
        $deadline = $j['deadline'];

        return [
            'id' => $j['id'],
            'title' => $j['title'],
            'slug' => $j['slug'],
            'vacancies' => $j['vacancies'],
            'salary' => $this->salaryOf($j),
            'min_salary' => $j['min_salary'],
            'max_salary' => $j['max_salary'],
            'salary_mode' => $j['salary_mode'],
            'custom_salary' => $j['custom_salary'],
            'job_type' => $j['job_type'],
            'deadline' => $deadline,
            'description' => $j['description'],
            'status' => $j['status'],
            'apply_on' => $j['apply_on'],
            'apply_email' => $j['apply_email'],
            'apply_url' => $j['apply_url'],
            'featured' => $j['featured'],
            'featured_until' => $this->dateJson($j['featured_until']),
            'highlight' => $j['highlight'],
            'highlight_until' => $this->dateJson($j['highlight_until']),
            'is_remote' => $j['is_remote'],
            'bookmarked' => $j['bookmarked'],
            'applied' => $j['applied'],
            'days_remaining' => Carbon::parse($deadline)->diffForHumans(null, CarbonInterface::DIFF_ABSOLUTE, true, 2),
            'deadline_active' => Carbon::parse($deadline)->format('Y-m-d') >= Carbon::now()->toDateString(),
            'posted_at' => $j['created_at'] ? Carbon::parse($j['created_at'])->toJSON() : null,
            'can_apply' => $j['apply_on'] === 'app',
            'location' => ($j['region'] !== null ? $j['region'].' , ' : '').$j['country'],
            'benefits' => $j['benefits'],
            'tags' => $j['tags'],
            'role' => $j['role'],
            'experience' => $j['experience'],
            'education' => $j['education'],
            'company' => [
                'name' => $j['company']['name'],
                'slug' => $j['company']['slug'],
                'logo' => $this->assetUrl($j['company']['logo']),
                'website' => $j['company']['website'],
                'address' => $j['company']['address'],
                'contact_info' => null,
                'social_links' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $h  hasil `HomeCatalog::home()`
     */
    public function home(array $h): array
    {
        return [
            'top_companies' => array_map(fn (array $c): array => [
                'name' => $c['name'],
                'username' => $c['username'],
                'company_logo' => $this->assetUrl($c['logo']),
                'country' => $c['country'],
                'open_jobs_count' => $c['open_jobs_count'],
            ], $h['top_companies']),
            'featured_jobs' => array_map(fn (array $j): array => $this->featuredItem($j), $h['featured_jobs']),
            'states' => $h['states'],
            'most_popular_vacancies' => array_map(fn (array $r): array => [
                'id' => $r['id'],
                'name' => $r['name'],
                'open_position_count' => $r['open_count'],
            ], $h['popular_roles']),
            'popular_categories' => array_map(fn (array $c): array => [
                'id' => $c['id'],
                'name' => $c['name'],
                'image' => $this->assetUrl($c['image']),
                'open_jobs_count' => $c['open_jobs_count'],
            ], $h['popular_categories']),
            'testimonial' => array_map(fn (array $t): array => [
                'name' => $t['name'],
                'designation' => $t['position'],
                'description' => $t['description'],
                'image' => $t['image'],
            ], $h['testimonials']),
            'top_categories' => $h['top_categories'],
        ];
    }

    /** @param  array<string, mixed>  $r */
    private function salaryOf(array $r): ?string
    {
        return $this->salary->label($r['salary_mode'], $r['min_salary'], $r['max_salary'], $r['custom_salary']);
    }

    private function jobUrl(string $slug): string
    {
        return url('/job/'.$slug);
    }

    private function assetUrl(?string $path): string
    {
        return asset($path ?: self::DEFAULT_IMAGE);
    }

    private function dateJson(?string $date): ?string
    {
        return $date ? Carbon::parse($date)->startOfDay()->toJSON() : null;
    }
}
