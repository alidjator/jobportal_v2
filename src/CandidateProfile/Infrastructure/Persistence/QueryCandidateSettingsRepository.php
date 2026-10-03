<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Src\CandidateProfile\Domain\CandidateSettingsRepository;
use Src\CandidateProfile\Domain\SocialMedia;

/**
 * Tabel milik context ini: candidates, candidate_skill, candidate_language, candidate_job_alerts,
 * social_links, contact_infos. Tabel `users` (nama/foto), `cities` (kota), `skills`/`professions`
 * (+ terjemahan) dan `languages` dibaca/ditulis seperti perilaku aplikasi lama.
 */
class QueryCandidateSettingsRepository implements CandidateSettingsRepository
{
    public function personal(int $userId, int $candidateId): array
    {
        $user = DB::table('users')->where('id', $userId)->first(['name', 'image']);
        $c = DB::table('candidates')->where('id', $candidateId)->first();

        return [
            'image_url' => PhotoUrl::of($user->image ?? null),
            'name' => $user->name ?? null,
            'title' => $c->title,
            'education_id' => (int) $c->education_id,
            'experience_id' => (int) $c->experience_id,
            'website' => $c->website,
            'date_of_birth' => $c->birth_date ? Carbon::parse($c->birth_date)->format('Y-m-d') : null,
            'experience_list' => Lookups::all('experiences'),
            'education_list' => Lookups::all('education'),
        ];
    }

    public function personalSummary(int $userId, int $candidateId): array
    {
        $name = DB::table('users')->where('id', $userId)->value('name');
        $c = DB::table('candidates')->where('id', $candidateId)->first();

        return [
            'name' => $name,
            'title' => $c->title,
            'experience_id' => (int) $c->experience_id,
            'education_id' => (int) $c->education_id,
            'website' => $c->website,
            'date_of_birth' => $c->birth_date,
            'image_url' => PhotoUrl::of($c->photo),
        ];
    }

    public function profile(int $candidateId): array
    {
        $c = DB::table('candidates')->where('id', $candidateId)->first();

        $skillIds = DB::table('candidate_skill')->where('candidate_id', $candidateId)->orderBy('id')->pluck('skill_id')->all();
        $languages = DB::table('candidate_language as p')
            ->join('candidate_languages as l', 'l.id', '=', 'p.candidate_language_id')
            ->where('p.candidate_id', $candidateId)->orderBy('p.id')
            ->get(['l.id', 'l.name'])->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all();

        return [
            'gender' => $c->gender,
            'marital_status' => $c->marital_status,
            'profession_id' => (int) $c->profession_id,
            'bio' => $c->bio,
            'availability' => $c->status,
            'available_in' => $c->available_in,
            'skills' => Lookups::named('skills', $skillIds),
            'languages' => $languages,
            'profession_list' => Lookups::all('professions'),
            'skill_list' => Lookups::all('skills'),
            'language_list' => Lookups::all('candidate_languages'),
        ];
    }

    public function social(int $userId): array
    {
        return [
            'social_media' => $this->socialLinks($userId),
            'social_media_list' => SocialMedia::options(),
        ];
    }

    public function socialLinks(int $userId): array
    {
        return DB::table('social_links')->where('user_id', $userId)->orderBy('id')->get()
            ->map(fn ($s) => ['id' => $s->id, 'social_media' => $s->social_media, 'url' => $s->url])->all();
    }

    public function contact(int $userId, int $candidateId): array
    {
        $c = DB::table('candidates')->where('id', $candidateId)->first();
        $contact = DB::table('contact_infos')->where('user_id', $userId)->first();
        $city = $c->district === null ? null : DB::table('cities')->where('bps_code', $c->district)->first();

        return [
            'contact_info' => [
                'phone' => $contact->phone ?? null,
                'secondary_phone' => $contact->secondary_phone ?? null,
                'whatsapp_no' => $c->whatsapp_number,
                'secondary_email' => $contact->secondary_email ?? null,
                'email' => $contact->email ?? null,
            ],
            'location' => [
                'country' => $c->country,
                'city' => $city === null ? null : Timestamps::convert((array) $city, 'created_at', 'updated_at'),
                'address' => $c->address,
                'latitude' => $c->lat,
                'longitude' => $c->long,
            ],
            'job_alerts' => DB::table('candidate_job_alerts')->where('candidate_id', $candidateId)->orderBy('id')->get()
                ->map(fn ($a) => Timestamps::convert((array) $a, 'created_at', 'updated_at'))->all(),
            'job_alert_role_list' => Lookups::all('job_roles'),
        ];
    }

    public function storedPhoto(int $candidateId): ?string
    {
        return DB::table('candidates')->where('id', $candidateId)->value('photo');
    }

    public function updatePersonal(int $userId, int $candidateId, array $data, ?string $newPhoto): void
    {
        DB::transaction(function () use ($userId, $candidateId, $data, $newPhoto) {
            DB::table('users')->where('id', $userId)->update(['name' => $data['name'], 'updated_at' => now()]);

            $candidate = [
                'title' => $data['title'],
                'experience_id' => $data['experience_id'],
                'education_id' => $data['education_id'],
                'website' => $data['website'],
                'birth_date' => $data['birth_date'],
                'updated_at' => now(),
            ];

            if ($newPhoto !== null) {
                $candidate['photo'] = $newPhoto;
                DB::table('users')->where('id', $userId)->update(['image' => $newPhoto]);
            }

            DB::table('candidates')->where('id', $candidateId)->update($candidate);
        });
    }

    public function updateProfile(int $candidateId, array $data, array $skills, array $languages): void
    {
        DB::transaction(function () use ($candidateId, $data, $skills, $languages) {
            $professionId = $this->resolveTranslated('professions', 'profession_translations', 'profession_id', (string) $data['profession']);

            DB::table('candidates')->where('id', $candidateId)->update([
                'gender' => $data['gender'],
                'marital_status' => $data['marital_status'],
                'bio' => $data['bio'],
                'profession_id' => $professionId,
                'status' => $data['status'],
                'available_in' => $data['available_in'],
                'updated_at' => now(),
            ]);

            if ($skills !== []) {
                DB::table('candidate_skill')->where('candidate_id', $candidateId)->delete();

                $ids = array_values(array_unique(array_map(
                    fn ($skill) => $this->resolveTranslated('skills', 'skill_translations', 'skill_id', (string) $skill),
                    $skills,
                )));

                DB::table('candidate_skill')->insert(array_map(fn ($id) => [
                    'candidate_id' => $candidateId,
                    'skill_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $ids));
            }

            if ($languages !== []) {
                $languages = array_values(array_unique($languages));
                DB::table('candidate_language')->where('candidate_id', $candidateId)
                    ->whereNotIn('candidate_language_id', $languages)->delete();

                $existing = DB::table('candidate_language')->where('candidate_id', $candidateId)
                    ->pluck('candidate_language_id')->all();

                $rows = [];
                foreach (array_diff($languages, $existing) as $id) {
                    $rows[] = ['candidate_id' => $candidateId, 'candidate_language_id' => $id, 'created_at' => now(), 'updated_at' => now()];
                }
                if ($rows !== []) {
                    DB::table('candidate_language')->insert($rows);
                }
            }
        });
    }

    public function replaceSocial(int $userId, array $links): void
    {
        DB::transaction(function () use ($userId, $links) {
            DB::table('social_links')->where('user_id', $userId)->delete();

            foreach ($links as $link) {
                DB::table('social_links')->insert([
                    'user_id' => $userId,
                    'social_media' => $link['social_media'],
                    'url' => $link['url'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function updateContact(int $userId, int $candidateId, array $data): void
    {
        DB::transaction(function () use ($userId, $candidateId, $data) {
            $contact = [
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'],
                'email' => $data['email'],
                'secondary_email' => $data['secondary_email'],
                'updated_at' => now(),
            ];

            if (DB::table('contact_infos')->where('user_id', $userId)->exists()) {
                DB::table('contact_infos')->where('user_id', $userId)->update($contact);
            } else {
                DB::table('contact_infos')->insert($contact + ['user_id' => $userId, 'created_at' => now()]);
            }

            $candidate = [
                'country' => $data['country'],
                'address' => $data['address'],
                'exact_location' => $data['exact_location'],
                'lat' => $data['lat'],
                'long' => $data['long'],
                'updated_at' => now(),
            ];

            if (! empty($data['whatsapp_number'])) {
                $candidate['whatsapp_number'] = $data['whatsapp_number'];
            }

            DB::table('candidates')->where('id', $candidateId)->update($candidate);
        });
    }

    /**
     * Cari id referensi dari id (numerik) atau nama terjemahan; buat baru (dengan terjemahan untuk
     * semua bahasa sistem) bila tidak ada, seperti `Profession::create`/`Skill::create` aplikasi lama.
     */
    private function resolveTranslated(string $table, string $translations, string $fk, string $value): int
    {
        $query = DB::table($translations)->where('name', $value);

        if (ctype_digit($value)) {
            $query->orWhere($fk, (int) $value);
        }

        $existing = $query->value($fk);

        if ($existing !== null) {
            return (int) $existing;
        }

        $id = (int) DB::table($table)->insertGetId(['created_at' => now(), 'updated_at' => now()]);

        $codes = DB::table('languages')->pluck('code')->all() ?: [config('app.fallback_locale')];

        foreach ($codes as $code) {
            DB::table($translations)->insert([
                $fk => $id, 'name' => $value, 'locale' => $code, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }
}
