<?php

namespace Src\Identity\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Domain\NewAccount;
use Src\Identity\Domain\UserRepository;

class EloquentUserRepository implements UserRepository
{
    public function findById(int $id): ?array
    {
        return UserModel::query()->find($id)?->toArray();
    }

    public function findByCredentials(string $email, string $password): ?array
    {
        $user = UserModel::query()->where('email', $email)->first();

        if ($user === null || ! is_string($user->getRawOriginal('password')) || ! Hash::check($password, $user->getRawOriginal('password'))) {
            return null;
        }

        return $user->toArray();
    }

    public function findByFirebaseUid(string $uid): ?array
    {
        return UserModel::query()->where('firebase_uid', $uid)->first()?->toArray();
    }

    public function findIdByEmail(string $email): ?int
    {
        $id = UserModel::query()->where('email', $email)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function emailExists(string $email): bool
    {
        return UserModel::query()->where('email', $email)->exists();
    }

    public function usernameExists(string $username): bool
    {
        return UserModel::query()->where('username', $username)->exists();
    }

    public function create(NewAccount $account): array
    {
        return DB::transaction(function () use ($account) {
            $attributes = array_filter([
                'firebase_uid' => $account->firebaseUid,
                'role' => $account->role,
                'name' => $account->name,
                'username' => $account->username,
                'email' => $account->email,
                'password' => $account->password, // di-hash oleh cast `hashed` pada UserModel
            ], fn ($value) => $value !== null);

            $user = UserModel::query()->create($attributes);

            $this->provision($user);

            return $user->toArray();
        });
    }

    public function changePassword(int $userId, string $plainPassword): void
    {
        UserModel::query()->whereKey($userId)->firstOrFail()->update(['password' => $plainPassword]);
    }

    /**
     * Padanan hook `User::created` aplikasi lama: status awal dari setting auto activation,
     * baris profil kosong (candidates / companies) dan contact_infos.
     *
     * Menulis ke tabel milik context lain (candidates, companies, contact_infos) karena ini bagian
     * atomik dari pembuatan akun; membaca `settings`, `industry_types`, `organization_types`, `team_sizes`.
     */
    private function provision(UserModel $user): void
    {
        $now = now();
        $setting = $user->role === 'company' ? 'employer_auto_activation' : 'candidate_account_auto_activation';

        // settings dibaca dari baris pertama; tanpa baris setting, akun tidak otomatis aktif (sama dengan aplikasi lama).
        if (! DB::table('settings')->orderBy('id')->value($setting)) {
            $user->update(['status' => 0]);
        }

        if ($user->role === 'company') {
            DB::table('companies')->insert([
                'user_id' => $user->id,
                'industry_type_id' => DB::table('industry_types')->orderBy('id')->value('id'),
                'organization_type_id' => DB::table('organization_types')->orderBy('id')->value('id'),
                'team_size_id' => DB::table('team_sizes')->orderBy('id')->value('id'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('candidates')->insert([
                'user_id' => $user->id,
                'name' => $user->username,
                'profile_mark' => 0,
                'education_mark' => 0,
                'experience_mark' => 0,
                'resume_mark' => 0,
                'photo' => $user->image,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('contact_infos')->insert([
            'user_id' => $user->id,
            'email' => $user->email,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
