<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\JobManagement\Ports\JobFormOptions;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

/** Membaca tabel referensi (kategori, role, benefit, tag, pengalaman, pendidikan, tipe, tipe gaji), read-only. */
class DbJobFormOptions implements JobFormOptions
{
    public function forCreate(): array
    {
        return [
            'jobCategories' => $this->named('job_categories', 'job_category'),
            'roles' => $this->named('job_roles', 'job_role'),
            'benefits' => $this->named('benefits', 'benefit'),
            'tags' => $this->named('tags', 'tag'),
            'experiences' => $this->named('experiences', 'experience', ['slug']),
            'educations' => $this->named('education', 'education', ['slug']),
            'job_types' => $this->named('job_types', 'job_type'),
            'salary_types' => $this->named('salary_types', 'salary_type', ['slug']),
        ];
    }

    public function forEdit(): array
    {
        return [
            'jobCategories' => $this->named('job_categories', 'job_category'),
            'roles' => $this->named('job_roles', 'job_role'),
            'benefits' => $this->named('benefits', 'benefit'),
            'tags' => $this->named('tags', 'tag'),
            'experiences' => $this->named('experiences', 'experience'),
            'educations' => $this->named('education', 'education'),
            'job_types' => $this->named('job_types', 'job_type'),
            'salary_types' => $this->named('salary_types', 'salary_type'),
        ];
    }

    /**
     * @param  list<string>  $extra  kolom tambahan dari tabel utama
     * @return list<array<string, mixed>>
     */
    private function named(string $table, string $singular, array $extra = []): array
    {
        $rows = DB::table($table)->orderBy('id')->get(['id', ...$extra]);
        $names = TranslatedNames::for("{$singular}_translations", "{$singular}_id", $rows->pluck('id'));

        return $rows->map(fn ($row) => array_merge((array) $row, ['name' => $names[$row->id] ?? null]))->all();
    }
}
