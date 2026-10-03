<?php

namespace Src\Recruitment\Infrastructure\JobBoard\Persistence;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\JobBoard\JobCatalog;
use Src\Recruitment\Domain\JobBoard\JobFilters;
use Src\Recruitment\Domain\JobBoard\JobPromotion;
use Src\Recruitment\Domain\JobBoard\Page;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

/**
 * Query Builder atas `jobs` (+ read-only `companies`/`users` untuk perusahaan, `candidate_resumes` untuk resume kandidat).
 */
final class QueryJobCatalog implements JobCatalog
{
    public function __construct(
        private readonly JobRows $rows,
        private readonly CountryScope $country,
        private readonly JobBoardSettings $settings,
    ) {}

    public function search(JobFilters $filters, ?int $candidateId, int $perPage, int $page): Page
    {
        $query = $this->rows->base()
            ->where('jobs.status', 'active')
            ->where('jobs.waiting_for_edit_approval', false);

        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();

        if ($filters->sortBy === 'latest') {
            $query->orderByDesc('jobs.id');
        }
        $query->orderByDesc('jobs.created_at')->orderByDesc('jobs.id');

        $items = $this->rows->select($query, $candidateId)->forPage($page, $perPage)->get();

        return new Page($this->rows->hydrate($items), $total, $perPage, $page);
    }

    public function findBySlug(string $slug, ?int $candidateId): ?array
    {
        $job = $this->rows->base()
            ->where('jobs.slug', $slug)
            ->select(['jobs.*', 'users.id as owner_id', 'users.name as owner_name', 'users.username as owner_username',
                'companies.logo as company_logo', 'companies.website as company_website', 'companies.address as company_address'])
            ->selectRaw('(select count(*) from bookmark_candidate_job bj where bj.job_id = jobs.id and bj.candidate_id = ?) as bookmarked_count', [$candidateId ?? 0])
            ->selectRaw('(select count(*) from applied_jobs aj where aj.job_id = jobs.id and aj.candidate_id = ?) as applied_count', [$candidateId ?? 0])
            ->first();

        if (! $job) {
            return null;
        }

        $benefitIds = DB::table('job_benefit')->where('job_id', $job->id)->orderBy('id')->pluck('benefit_id');
        $benefitNames = TranslatedNames::for('benefit_translations', 'benefit_id', $benefitIds);
        $tagIds = DB::table('job_tag')->where('job_id', $job->id)->orderBy('id')->pluck('tag_id');
        $tagNames = TranslatedNames::for('tag_translations', 'tag_id', $tagIds);

        $name = fn (string $table, string $fk, mixed $id): ?string => $id
            ? (TranslatedNames::for($table, $fk, [$id])[$id] ?? null)
            : null;

        return [
            'id' => (int) $job->id,
            'title' => $job->title,
            'slug' => $job->slug,
            'vacancies' => $job->vacancies,
            'min_salary' => $job->min_salary,
            'max_salary' => $job->max_salary,
            'salary_mode' => $job->salary_mode,
            'custom_salary' => $job->custom_salary,
            'job_type' => $name('job_type_translations', 'job_type_id', $job->job_type_id),
            'deadline' => $job->deadline,
            'description' => $job->description,
            'status' => $job->status,
            'apply_on' => $job->apply_on,
            'apply_email' => $job->apply_email,
            'apply_url' => $job->apply_url,
            'featured' => JobPromotion::isActive($job->featured, $job->featured_until, $this->settings->featuredDays()),
            'featured_until' => $job->featured_until,
            'highlight' => JobPromotion::isActive($job->highlight, $job->highlight_until, $this->settings->highlightDays()),
            'highlight_until' => $job->highlight_until,
            'is_remote' => $job->is_remote,
            'bookmarked' => (int) $job->bookmarked_count > 0,
            'applied' => (int) $job->applied_count > 0,
            'created_at' => $job->created_at,
            'region' => $job->region,
            'country' => $job->country,
            'category_id' => $job->category_id,
            'company_id' => $job->company_id,
            'company_user_id' => $job->owner_id,
            'benefits' => array_values(array_filter($benefitIds->map(fn ($id) => $benefitNames[$id] ?? null)->all(), fn ($v) => $v !== null)),
            'tags' => array_values(array_filter($tagIds->map(fn ($id) => $tagNames[$id] ?? null)->all(), fn ($v) => $v !== null)),
            'role' => $name('job_role_translations', 'job_role_id', $job->role_id),
            'experience' => $name('experience_translations', 'experience_id', $job->experience_id),
            'education' => $name('education_translations', 'education_id', $job->education_id),
            'company' => [
                'name' => $job->owner_name,
                'slug' => $job->owner_username,
                'logo' => $job->company_logo,
                'website' => $job->company_website,
                'address' => $job->company_address,
            ],
        ];
    }

    public function related(int $jobId, ?int $categoryId, ?int $candidateId): array
    {
        $query = $this->rows->base()
            ->where('jobs.status', 'active')
            ->where('jobs.waiting_for_edit_approval', false)
            ->where('jobs.id', '!=', $jobId)
            ->where('jobs.category_id', $categoryId);

        $this->country->apply($query, 'jobs.country', singleBaseFirst: true);

        $items = $this->rows->select($query, $candidateId)
            ->orderByDesc('jobs.created_at')->orderByDesc('jobs.id')
            ->limit(18)->get();

        return $this->rows->hydrate($items);
    }

    public function resumesOf(int $candidateId): array
    {
        return DB::table('candidate_resumes')->where('candidate_id', $candidateId)->orderBy('id')->get()
            ->map(fn (object $r): array => [
                'id' => (int) $r->id,
                'candidate_id' => (int) $r->candidate_id,
                'name' => $r->name,
                'file' => $r->file,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at)->toJSON() : null,
                'updated_at' => $r->updated_at ? Carbon::parse($r->updated_at)->toJSON() : null,
                'file_size' => $this->fileSize($r->file),
                'file_url' => asset($r->file),
            ])->all();
    }

    private function fileSize(?string $file): string
    {
        $path = $file ? public_path($file) : null;

        if ($path && is_file($path)) {
            return round(filesize($path) / 1024 / 1024, 4).' MB';
        }

        return '0 MB';
    }

    private function applyFilters(Builder $query, JobFilters $f): void
    {
        if ($f->company !== null) {
            $query->where('users.username', $f->company);
        }

        if ($f->keyword !== null) {
            $query->where('jobs.title', 'LIKE', '%'.$f->keyword.'%');
        }

        if ($f->category !== null) {
            $query->where('jobs.category_id', $f->category);
        }

        if ($f->jobRole !== null) {
            $query->where('jobs.role_id', $f->jobRole);
        }

        if ($f->priceMin !== null) {
            $query->where('jobs.min_salary', '>=', $f->priceMin);
        }

        if ($f->priceMax !== null) {
            $query->where('jobs.max_salary', '<=', $f->priceMax);
        }

        if ($f->tag !== null) {
            $tagId = DB::table('tag_translations')->where('name', $f->tag)->value('tag_id');

            if ($tagId) {
                $query->whereExists(fn ($q) => $q->selectRaw('1')->from('job_tag')
                    ->whereColumn('job_tag.job_id', 'jobs.id')->where('job_tag.tag_id', $tagId));
            }
        }

        if ($f->lat !== null && $f->long !== null) {
            // Pencarian radius (haversine, km) -- dikelompokkan agar tidak merusak filter status lain.
            $query->where(function (Builder $q) use ($f): void {
                $q->whereRaw(
                    '(6371 * acos(least(1, cos(radians(?)) * cos(radians(jobs.`lat`)) * cos(radians(jobs.`long`) - radians(?)) + sin(radians(?)) * sin(radians(jobs.`lat`))))) <= ?',
                    [$f->lat, $f->long, $f->lat, $f->radius],
                );

                if ($f->location !== null) {
                    $q->orWhere('jobs.country', 'LIKE', '%'.$f->location.'%');
                }
            });
        } elseif ($f->location !== null) {
            $query->where('jobs.country', 'LIKE', '%'.$f->location.'%');
        }

        $this->country->apply($query);

        if ($f->sortBy === 'featured') {
            $query->where('jobs.featured', 1);
        }

        if ($f->experience !== null) {
            $query->whereIn('jobs.experience_id', DB::table('experience_translations')->where('name', $f->experience)->select('experience_id'));
        }

        if ($f->education !== null) {
            $query->whereIn('jobs.education_id', DB::table('education_translations')->where('name', $f->education)->select('education_id'));
        }

        if ($f->isRemote) {
            $query->where('jobs.is_remote', 1);
        }

        if ($f->jobType !== null) {
            $query->whereIn('jobs.job_type_id', DB::table('job_type_translations')->where('name', $f->jobType)->select('job_type_id'));
        }
    }
}
