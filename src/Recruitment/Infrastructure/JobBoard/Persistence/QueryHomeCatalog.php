<?php

namespace Src\Recruitment\Infrastructure\JobBoard\Persistence;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\JobBoard\HomeCatalog;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

/**
 * Query beranda. Membaca `jobs` (Recruitment) serta, read-only dan hanya untuk statistik/tampilan,
 * `companies`, `candidates`, `users` (perusahaan teratas), `job_roles`/`job_categories` (+ terjemahan)
 * dan `testimonials` (Content).
 */
final class QueryHomeCatalog implements HomeCatalog
{
    public function __construct(
        private readonly JobRows $rows,
        private readonly CountryScope $country,
    ) {}

    public function home(?int $candidateId): array
    {
        $today = Carbon::now()->toDateString();

        $open = fn (): Builder => DB::table('jobs')->where('jobs.waiting_for_edit_approval', false)
            ->where('jobs.status', 'active')->where('jobs.deadline', '>=', $today);

        $states = [
            'live_jobs' => $this->country->apply($open())->count(),
            'new_jobs' => $this->country->apply(
                DB::table('jobs')->where('jobs.waiting_for_edit_approval', false)->where('jobs.status', 'active')
                    ->where('jobs.created_at', '>=', Carbon::now()->subDays(7)->toDateString()),
            )->count(),
            'companies' => DB::table('companies')->count(),
            'candidates' => DB::table('candidates')->count(),
        ];

        $featured = $this->rows->base()
            ->where('jobs.waiting_for_edit_approval', false)
            ->where('jobs.featured', 1)
            ->where('jobs.status', 'active');
        $this->country->apply($featured);
        $featuredJobs = $this->rows->hydrate(
            $this->rows->select($featured, $candidateId)->orderBy('jobs.id')->limit(6)->get(),
        );

        return [
            'states' => $states,
            'featured_jobs' => $featuredJobs,
            'popular_roles' => $this->popular('job_roles', 'role_id', 'job_role_translations', 'job_role_id', $today),
            'popular_categories' => $this->popular('job_categories', 'category_id', 'job_category_translations', 'job_category_id', $today),
            'top_companies' => $this->topCompanies(),
            'top_categories' => $this->topCategories(),
            'testimonials' => $this->testimonials(),
        ];
    }

    /**
     * 8 role/kategori dengan lowongan terbanyak; `open_count` = lowongan aktif belum lewat deadline
     * pada negara aktif, hasil diurutkan naik menurut `open_count` (sama dengan aplikasi lama).
     *
     * `open_jobs_count` = lowongan terbuka tanpa batas negara (accessor lama `JobCategory::open_jobs_count`).
     *
     * @return list<array{id:int, name:?string, image:?string, open_count:int, open_jobs_count:int}>
     */
    private function popular(string $table, string $jobColumn, string $translationTable, string $translationKey, string $today): array
    {
        $items = DB::table($table)
            ->select($table === 'job_categories' ? ["$table.id", "$table.image"] : ["$table.id"])
            ->selectRaw("(select count(*) from jobs j where j.$jobColumn = $table.id) as jobs_count")
            ->orderByDesc('jobs_count')->orderBy("$table.id")
            ->limit(8)->get();

        $names = TranslatedNames::for($translationTable, $translationKey, $items->pluck('id'));

        $result = $items->map(function (object $item) use ($jobColumn, $today, $names, $table): array {
            $open = DB::table('jobs')->where($jobColumn, $item->id)->where('status', 'active')->where('deadline', '>=', $today);
            $this->country->apply($open, 'jobs.country', singleBaseFirst: true);

            return [
                'id' => (int) $item->id,
                'name' => $names[$item->id] ?? null,
                'image' => $table === 'job_categories' ? $item->image : null,
                'open_count' => $open->count(),
                'open_jobs_count' => DB::table('jobs')->where($jobColumn, $item->id)->where('status', 'active')->where('deadline', '>=', $today)->count(),
            ];
        })->all();

        usort($result, fn (array $a, array $b) => $a['open_count'] <=> $b['open_count']);

        return $result;
    }

    /** @return list<array{name:?string, username:?string, logo:?string, country:?string, open_jobs_count:int}> */
    private function topCompanies(): array
    {
        $count = DB::table('jobs')->selectRaw('count(*)')
            ->whereColumn('jobs.company_id', 'companies.id')->where('jobs.status', 'active');
        $this->country->apply($count);

        return DB::table('companies')
            ->join('users', 'users.id', '=', 'companies.user_id')
            ->select(['users.name', 'users.username', 'companies.logo', 'companies.country'])
            ->selectSub($count, 'jobs_count')
            ->orderByDesc('jobs_count')->orderBy('companies.id')
            ->limit(9)->get()
            ->map(fn (object $c): array => [
                'name' => $c->name,
                'username' => $c->username,
                'logo' => $c->logo,
                'country' => $c->country,
                'open_jobs_count' => (int) $c->jobs_count,
            ])->all();
    }

    /** @return list<array{id:int, name:?string}> */
    private function topCategories(): array
    {
        $items = DB::table('job_categories')->select('job_categories.id')
            ->selectRaw('(select count(*) from jobs j where j.category_id = job_categories.id) as jobs_count')
            ->orderByDesc('jobs_count')->orderBy('job_categories.id')->limit(8)->get();

        $names = TranslatedNames::for('job_category_translations', 'job_category_id', $items->pluck('id'));

        return $items->map(fn (object $c): array => ['id' => (int) $c->id, 'name' => $names[$c->id] ?? null])->all();
    }

    /** @return list<array{name:string, position:?string, description:string, image:string}> */
    private function testimonials(): array
    {
        return DB::table('testimonials')->orderBy('id')->get()
            ->map(fn (object $t): array => [
                'name' => $t->name,
                'position' => $t->position,
                'description' => $t->description,
                'image' => $t->image ?: 'backend/image/default.png',
            ])->all();
    }
}
