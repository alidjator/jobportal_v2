<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Recruitment\Domain\JobManagement\JobSlug;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

class EloquentJobRepository implements JobRepository
{
    public function findOwnedBySlug(int $companyId, string $slug): ?array
    {
        return JobRecord::query()->where('company_id', $companyId)->where('slug', $slug)->orderBy('id')->first()?->toArray();
    }

    public function findPendingEdit(int $companyId, int $parentJobId): ?array
    {
        return JobRecord::query()->where('parent_job_id', $parentJobId)->where('company_id', $companyId)->orderBy('id')->first()?->toArray();
    }

    public function ownsJob(int $companyId, int $jobId): bool
    {
        return JobRecord::query()->whereKey($jobId)->where('company_id', $companyId)->exists();
    }

    public function create(array $attributes): array
    {
        $attributes['slug'] = $this->slugFor((string) $attributes['title'], null);

        return JobRecord::query()->create($attributes)->fresh()->toArray();
    }

    public function update(int $jobId, array $attributes): array
    {
        $record = JobRecord::query()->findOrFail($jobId);

        // Seperti model lama: slug mengikuti judul setiap kali judul diisi.
        if (isset($attributes['title'])) {
            $attributes['slug'] = $this->slugFor((string) $attributes['title'], $jobId);
        }

        $record->update($attributes);

        return $record->fresh()->toArray();
    }

    public function replicate(int $jobId, array $overrides): array
    {
        $source = JobRecord::query()->findOrFail($jobId);

        $attributes = array_diff_key($source->getAttributes(), array_flip(['id', 'created_at', 'updated_at', 'slug']));
        $attributes = array_merge($attributes, ['total_views' => 0], $overrides);
        $attributes['slug'] = $this->slugFor((string) $attributes['title'], null);

        return JobRecord::query()->create($attributes)->fresh()->toArray();
    }

    public function paginateForCompany(int $companyId, string $status, int $perPage): array
    {
        $appliedCount = DB::table('applied_jobs')
            ->join('candidates', 'candidates.id', '=', 'applied_jobs.candidate_id')
            ->whereColumn('applied_jobs.job_id', 'jobs.id')
            ->selectRaw('count(*)');

        $paginator = JobRecord::query()
            ->where('company_id', $companyId)
            ->where('status', $status)
            ->select(['id', 'title', 'company_id', 'country', 'max_salary', 'min_salary', 'job_type_id', 'slug', 'deadline', 'status'])
            ->selectSub($appliedCount, 'applied_jobs_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $result = $paginator->toArray();
        $typeNames = TranslatedNames::for('job_type_translations', 'job_type_id', array_filter(array_column($result['data'], 'job_type_id')));

        $result['data'] = array_map(function (array $job) use ($typeNames) {
            // Bentuk relasi lama: company:id (user tidak ikut terpilih) dan job_type:id,name.
            $job['company'] = ['id' => $job['company_id'], 'user' => null];
            $job['job_type'] = $job['job_type_id'] === null
                ? null
                : ['id' => $job['job_type_id'], 'name' => $typeNames[$job['job_type_id']] ?? null];

            return $job;
        }, $result['data']);

        return $result;
    }

    public function attachTags(int $jobId, array $names, bool $replace): void
    {
        if ($names === []) {
            return;
        }

        $tagIds = array_values(array_unique(array_map(fn (string $name) => $this->tagIdFor($name), $names)));

        $existing = DB::table('job_tag')->where('job_id', $jobId)->pluck('tag_id')->all();

        if ($replace) {
            DB::table('job_tag')->where('job_id', $jobId)->whereNotIn('tag_id', $tagIds)->delete();
            $tagIds = array_values(array_diff($tagIds, $existing));
        }

        DB::table('job_tag')->insert(array_map(fn (int $id) => ['job_id' => $jobId, 'tag_id' => $id], $tagIds));
    }

    public function tagsAndBenefits(int $jobId): array
    {
        return [
            'tags' => $this->related($jobId, 'tags', 'job_tag', 'tag_id', 'tag_translations', 'tag'),
            'benefits' => $this->related($jobId, 'benefits', 'job_benefit', 'benefit_id', 'benefit_translations', 'benefit'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function related(int $jobId, string $table, string $pivot, string $pivotKey, string $translations, string $singular): array
    {
        $rows = DB::table($table)
            ->join($pivot, "{$pivot}.{$pivotKey}", '=', "{$table}.id")
            ->where("{$pivot}.job_id", $jobId)
            ->orderBy("{$pivot}.id")
            ->get(["{$table}.*"])
            ->all();

        $decorated = TranslatableRows::decorate($rows, $translations, "{$singular}_id");

        return array_values(array_map(
            fn (array $row) => $row + ['pivot' => ['job_id' => $jobId, $pivotKey => $row['id']]],
            $decorated,
        ));
    }

    private function slugFor(string $title, ?int $exceptJobId): string
    {
        return JobSlug::make($title, fn (string $slug) => JobRecord::query()
            ->where('slug', $slug)
            ->when($exceptJobId, fn ($q) => $q->where('id', '!=', $exceptJobId))
            ->exists());
    }

    /** Cari tag dari nama (bahasa apa pun); bila belum ada buat tag + terjemahan untuk semua bahasa. */
    private function tagIdFor(string $name): int
    {
        $id = DB::table('tag_translations')->where('name', $name)->value('tag_id');
        if ($id) {
            return (int) $id;
        }

        $now = now();
        $tagId = DB::table('tags')->insertGetId(['created_at' => $now, 'updated_at' => $now]);

        $locales = DB::table('languages')->pluck('code')->all() ?: [app()->getLocale()];
        DB::table('tag_translations')->insert(array_map(fn (string $code) => [
            'tag_id' => $tagId, 'name' => $name, 'locale' => $code, 'created_at' => $now, 'updated_at' => $now,
        ], $locales));

        return $tagId;
    }
}
