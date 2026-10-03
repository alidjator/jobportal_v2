<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Src\CandidateProfile\Domain\CandidateDirectory;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

/**
 * Tabel milik context ini: candidates (+ relasi skills/languages/experiences/educations).
 * Dibaca read-only: `users` (identitas publik), `companies` & `bookmark_company` (flag bookmarked
 * untuk company yang login), tabel referensi (experiences, education, professions, skills).
 */
class QueryCandidateDirectory implements CandidateDirectory
{
    private const RADIUS_KM = 50;

    public function search(array $filters, int $perPage, ?int $companyUserId): array
    {
        $query = DB::table('candidates as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('u.role', 'candidate')
            ->where('c.visibility', 1);

        $this->applyFilters($query, $filters);

        $rows = $query->orderBy('c.created_at', ($filters['sortby'] ?? 'latest') === 'latest' || empty($filters['sortby']) ? 'desc' : 'asc')
            ->orderBy('c.id', 'desc')
            ->paginate($perPage, [
                'c.id', 'c.experience_id', 'c.profession_id', 'c.title', 'c.country', 'c.photo',
                'u.username', 'u.email', 'u.role', 'u.name',
            ])->items();

        $experiences = TranslatedNames::for('experience_translations', 'experience_id', array_filter(array_column($rows, 'experience_id')));
        $professions = TranslatedNames::for('profession_translations', 'profession_id', array_filter(array_column($rows, 'profession_id')));

        $bookmarked = [];
        if ($companyUserId !== null && $rows !== []) {
            $companyId = DB::table('companies')->where('user_id', $companyUserId)->value('id');
            if ($companyId !== null) {
                $bookmarked = DB::table('bookmark_company')->where('company_id', $companyId)
                    ->whereIn('candidate_id', array_column($rows, 'id'))->pluck('candidate_id')->flip()->all();
            }
        }

        return array_map(fn ($r) => [
            'id' => $r->id,
            'experience' => $experiences[$r->experience_id] ?? null,
            'profession' => $professions[$r->profession_id] ?? null,
            'username' => $r->username,
            'email' => $r->email,
            'role' => $r->role,
            'name' => $r->name,
            'title' => $r->title,
            'country' => $r->country,
            'photo_url' => PhotoUrl::of($r->photo),
            'bookmarked' => isset($bookmarked[$r->id]),
        ], $rows);
    }

    /** @param  array<string,mixed>  $f */
    private function applyFilters(Builder $query, array $f): void
    {
        $query->where('c.status', $this->filled($f, 'status') ? $f['status'] : 'available');

        if ($this->filled($f, 'keyword')) {
            $query->where('u.name', 'like', '%'.$f['keyword'].'%');
        }

        if ($this->filled($f, 'lat') && $this->filled($f, 'long')) {
            $haversine = '(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(c.lat)) * cos(radians(c.`long`) - radians(?)) + sin(radians(?)) * sin(radians(c.lat))))))';
            $query->where(function (Builder $q) use ($f, $haversine) {
                $q->whereRaw("$haversine <= ?", [$f['lat'], $f['long'], $f['lat'], self::RADIUS_KM]);

                if ($this->filled($f, 'location')) {
                    $q->orWhere('c.country', $f['location']);
                }
            });
        }

        if ($this->filled($f, 'profession')) {
            $query->where('c.profession_id', $f['profession']);
        }

        foreach (['experience' => ['experiences', 'experience_translations', 'experience_id', 'c.experience_id'],
            'education' => ['education', 'education_translations', 'education_id', 'c.education_id']] as $key => [$table, $translations, $fk, $column]) {
            if ($this->filled($f, $key) && $f[$key] !== 'all') {
                // Lama: `whereName()` pada tabel tanpa kolom name (error); sekarang cocokkan nama terjemahan.
                $ids = DB::table($translations)->where('name', $f[$key])->pluck($fk)->all();
                $query->whereIn($column, $ids);
            }
        }

        if ($this->filled($f, 'gender')) {
            $query->where('c.gender', $f['gender']);
        }

        if ($this->filled($f, 'language')) {
            $query->whereExists(fn (Builder $q) => $q->from('candidate_language as cl')->selectRaw('1')
                ->whereColumn('cl.candidate_id', 'c.id')->where('cl.candidate_language_id', $f['language']));
        }

        if ($this->filled($f, 'skills')) {
            $skills = array_values(array_filter(Arr::wrap($f['skills']), fn ($s) => is_scalar($s) && $s !== ''));
            if ($skills !== []) {
                $query->whereExists(fn (Builder $q) => $q->from('candidate_skill as cs')->selectRaw('1')
                    ->whereColumn('cs.candidate_id', 'c.id')->whereIn('cs.skill_id', $skills));
            }
        }
    }

    /** @param  array<string,mixed>  $f */
    private function filled(array $f, string $key): bool
    {
        return isset($f[$key]) && $f[$key] !== '' && $f[$key] !== [];
    }

    public function detail(string $username): ?array
    {
        $user = DB::table('users')->where('username', $username)->where('role', 'candidate')->first();
        $c = $user ? DB::table('candidates')->where('user_id', $user->id)->first() : null;

        // visibility = 0: kandidat menyembunyikan profilnya dari publik.
        if ($user === null || $c === null || ! $c->visibility) {
            return null;
        }

        $contact = DB::table('contact_infos')->where('user_id', $user->id)->first();

        $skills = DB::table('candidate_skill')->where('candidate_id', $c->id)->orderBy('id')->pluck('skill_id')->all();
        $skills = Lookups::named('skills', $skills);
        $languages = DB::table('candidate_language as p')
            ->join('candidate_languages as l', 'l.id', '=', 'p.candidate_language_id')
            ->where('p.candidate_id', $c->id)->orderBy('p.id')->get(['l.id', 'l.name'])
            ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all();

        $candidate = [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'role_id' => $c->role_id,
            'profession_id' => $c->profession_id,
            'experience_id' => $c->experience_id,
            'education_id' => $c->education_id,
            'title' => $c->title,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'gender' => $c->gender,
            'website' => $c->website,
            'photo' => PhotoUrl::of($c->photo),
            'cv' => $c->cv,
            'bio' => $c->bio,
            'marital_status' => $c->marital_status,
            'birth_date' => $c->birth_date ? Carbon::parse($c->birth_date)->format('d F, Y') : null,
            'visibility' => $c->visibility,
            'cv_visibility' => $c->cv_visibility,
            'profile_complete' => $c->profile_complete,
            'created_at' => Timestamps::iso($c->created_at),
            'updated_at' => Timestamps::iso($c->updated_at),
            'address' => $c->address,
            'neighborhood' => $c->neighborhood,
            'locality' => $c->locality,
            'place' => $c->place,
            'district' => $c->district,
            'postcode' => $c->postcode,
            'region' => $c->region,
            'country' => $c->country,
            'long' => $c->long,
            'lat' => $c->lat,
            'status' => $c->status,
            'available_in' => $c->available_in,
            'whatsapp_number' => $c->whatsapp_number,
            'phone' => $c->phone,
            'name' => $c->name,
            'religion_id' => $c->religion_id,
            'full_address' => $c->region.($c->region !== null ? ' , ' : '').$c->country,
            'bookmark_candidates_count' => DB::table('bookmark_company')->where('candidate_id', $c->id)->count(),
            'experiences' => DB::table('candidate_experiences')->where('candidate_id', $c->id)->orderByDesc('start')->get()
                ->map(fn ($e) => $this->experience((array) $e))->all(),
            'educations' => DB::table('candidate_education')->where('candidate_id', $c->id)->orderByDesc('year')->get()
                ->map(fn ($e) => Timestamps::convert((array) $e, 'created_at', 'updated_at'))->all(),
            'experience' => $c->experience_id ? Lookups::named('experiences', [$c->experience_id])[0] : null,
            'education' => $c->education_id ? Lookups::named('education', [$c->education_id])[0] : null,
            'profession' => $c->profession_id ? Lookups::named('professions', [$c->profession_id])[0] : null,
            'languages' => $languages,
            'skills' => $skills,
        ];

        return [
            'candidate' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'image' => $user->image,
                'role' => $user->role,
                'created_at' => Timestamps::iso($user->created_at),
                'updated_at' => Timestamps::iso($user->updated_at),
                'image_url' => PhotoUrl::of($user->image),
                'contact_info' => $contact === null ? null : Timestamps::convert((array) $contact, 'created_at', 'updated_at'),
                'social_info' => DB::table('social_links')->where('user_id', $user->id)->orderBy('id')->get()
                    ->map(fn ($s) => Timestamps::convert((array) $s, 'created_at', 'updated_at'))->all(),
                'candidate' => $candidate,
            ],
            'skills' => implode(', ', array_column($skills, 'name')),
            'languages' => implode(', ', array_column($languages, 'name')),
        ];
    }

    /**
     * @param  array<string,mixed>  $e
     * @return array<string,mixed>
     */
    private function experience(array $e): array
    {
        $e = Timestamps::convert($e, 'created_at', 'updated_at');
        $e['formatted_start'] = $e['start'] ? Carbon::parse($e['start'])->format('d M Y') : null;
        $e['formatted_end'] = $e['end'] ? Carbon::parse($e['end'])->format('d M Y') : null;

        return $e;
    }
}
