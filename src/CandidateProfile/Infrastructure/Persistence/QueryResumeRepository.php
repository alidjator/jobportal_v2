<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\CandidateProfile\Domain\ResumeRepository;

/** Tabel candidate_resumes. */
class QueryResumeRepository implements ResumeRepository
{
    public function listFor(int $candidateId): array
    {
        return DB::table('candidate_resumes')->where('candidate_id', $candidateId)
            ->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'file' => $r->file,
                'file_size' => self::fileSize($r->file),
            ])->all();
    }

    public function findOwned(int $candidateId, int $resumeId): ?array
    {
        $owned = DB::table('candidate_resumes')->where('id', $resumeId)->where('candidate_id', $candidateId)->exists();

        return $owned ? $this->full($resumeId) : null;
    }

    public function create(int $candidateId, string $name, string $file): array
    {
        $id = DB::table('candidate_resumes')->insertGetId([
            'candidate_id' => $candidateId,
            'name' => $name,
            'file' => $file,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->full($id);
    }

    public function update(int $resumeId, string $name, ?string $file): array
    {
        $values = ['name' => $name, 'updated_at' => now()];

        if ($file !== null) {
            $values['file'] = $file;
        }

        DB::table('candidate_resumes')->where('id', $resumeId)->update($values);

        return $this->full($resumeId);
    }

    public function delete(int $resumeId): void
    {
        DB::table('candidate_resumes')->where('id', $resumeId)->delete();
    }

    /** Bentuk model `CandidateResume` lama (dengan accessor `file_size` & `file_url`). */
    private function full(int $id): array
    {
        $r = (array) DB::table('candidate_resumes')->where('id', $id)->first();
        $r = Timestamps::convert($r, 'created_at', 'updated_at');
        $r['file_size'] = self::fileSize($r['file']);
        $r['file_url'] = asset($r['file']);

        return $r;
    }

    public static function fileSize(?string $file): string
    {
        if ($file && is_file(public_path($file))) {
            return round(filesize(public_path($file)) / 1024 / 1024, 4).' MB';
        }

        return '0 MB';
    }
}
