<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

/**
 * Pembacaan read-only tabel referensi milik context ReferenceData
 * (experiences, education, professions, skills, job_roles, candidate_languages)
 * untuk daftar pilihan di halaman settings dan nama pada profil kandidat.
 */
final class Lookups
{
    /** Tabel referensi ber-terjemahan: tabel => [tabel terjemahan, foreign key]. */
    private const TRANSLATED = [
        'experiences' => ['experience_translations', 'experience_id'],
        'education' => ['education_translations', 'education_id'],
        'professions' => ['profession_translations', 'profession_id'],
        'skills' => ['skill_translations', 'skill_id'],
        'job_roles' => ['job_role_translations', 'job_role_id'],
    ];

    /**
     * Semua baris `{id, name}` (urut id), seperti `Model::all()->map(...)` aplikasi lama.
     *
     * @return list<array{id:int,name:string|null}>
     */
    public static function all(string $table): array
    {
        if ($table === 'candidate_languages') {
            return DB::table($table)->orderBy('id')->get(['id', 'name'])
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all();
        }

        $ids = DB::table($table)->orderBy('id')->pluck('id')->all();

        return self::named($table, $ids);
    }

    /**
     * @param  iterable<int>  $ids
     * @return list<array{id:int,name:string|null}>
     */
    public static function named(string $table, iterable $ids): array
    {
        [$translations, $fk] = self::TRANSLATED[$table];
        $ids = collect($ids)->values()->all();
        $names = TranslatedNames::for($translations, $fk, $ids);

        return array_map(fn ($id) => ['id' => (int) $id, 'name' => $names[$id] ?? null], $ids);
    }

    public static function name(string $table, ?int $id): ?string
    {
        return $id === null ? null : (self::named($table, [$id])[0]['name'] ?? null);
    }
}
