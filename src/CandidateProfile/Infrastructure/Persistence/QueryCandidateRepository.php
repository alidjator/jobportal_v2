<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\CandidateProfile\Domain\CandidateRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Tabel milik context ini: candidates (+ social_links, contact_infos yang ikut profil).
 * Tabel `users`, `jobs`, `applied_jobs`, `bookmark_candidate_job`, `notifications`
 * dibaca read-only (identitas user & hitungan dashboard).
 */
class QueryCandidateRepository implements CandidateRepository
{
    public function ensureIdForUser(int $userId): int
    {
        $id = DB::table('candidates')->where('user_id', $userId)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $user = DB::table('users')->where('id', $userId)->first(['username', 'image']);

        if ($user === null) {
            throw new EntityNotFound('User not found');
        }

        // Nilai awal sama dengan hook `User::created` aplikasi lama (kolom *_mark tidak punya default).
        return (int) DB::table('candidates')->insertGetId([
            'user_id' => $userId,
            'name' => $user->username,
            'photo' => $user->image,
            'profile_mark' => 0,
            'education_mark' => 0,
            'experience_mark' => 0,
            'resume_mark' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function profile(int $candidateId): array
    {
        $c = DB::table('candidates')->where('id', $candidateId)->first();
        $user = $c ? DB::table('users')->where('id', $c->user_id)->first() : null;

        if ($c === null || $user === null) {
            return [];
        }

        $contact = DB::table('contact_infos')->where('user_id', $user->id)->first();

        return [
            'id' => $c->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'name' => $user->name,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'role_id' => $c->role_id,
            'profession_id' => $c->profession_id,
            'experience_id' => $c->experience_id,
            'education_id' => $c->education_id,
            'title' => $c->title,
            'gender' => $c->gender,
            'website' => $c->website,
            'cv' => $c->cv,
            'bio' => $c->bio,
            'marital_status' => $c->marital_status,
            'religion_id' => $c->religion_id,
            'birth_date' => $c->birth_date,
            'cv_visibility' => $c->cv_visibility,
            'profile_complete' => $c->profile_complete,
            'address' => $c->address,
            'neighborhood' => $c->neighborhood,
            'locality' => $c->locality,
            'place' => $c->place,
            'district' => $c->district,
            'postcode' => $c->postcode,
            'region' => $c->region,
            'country' => $c->country,
            'photo_url' => PhotoUrl::of($c->photo),
            'received_job_alert' => $c->received_job_alert,
            'social' => DB::table('social_links')->where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn ($s) => ['social_media' => $s->social_media, 'url' => $s->url])->all(),
            'contactinfo' => $contact === null ? null : [
                'phone' => $contact->phone,
                'secondary_phone' => $contact->secondary_phone,
                'email' => $contact->email,
                'secondary_email' => $contact->secondary_email,
            ],
        ];
    }

    public function dashboard(int $candidateId, int $userId): array
    {
        return [
            'profileComplated' => DB::table('candidates')->where('id', $candidateId)->value('profile_complete'),
            // Relasi lama: belongsToMany(Job) => hanya lowongan yang masih ada.
            'appliedJobs' => DB::table('applied_jobs as p')->join('jobs as j', 'j.id', '=', 'p.job_id')
                ->where('p.candidate_id', $candidateId)->count(),
            'favoriteJobs' => DB::table('bookmark_candidate_job as p')->join('jobs as j', 'j.id', '=', 'p.job_id')
                ->where('p.candidate_id', $candidateId)->count(),
            // `notifiable_type` = kelas model user (lama: App\Models\User).
            'notifications' => DB::table('notifications')->where('notifiable_id', $userId)
                ->where('notifiable_type', 'like', '%User%')->count(),
        ];
    }
}
