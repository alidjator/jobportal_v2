<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\CandidateProfile\Domain\AccountRepository;

/**
 * Tabel `users` & `personal_access_tokens` milik Identity: ditulis di sini hanya untuk
 * dua use case settings kandidat (ganti password, hapus akun) sesuai kontrak API lama.
 */
class QueryAccountRepository implements AccountRepository
{
    public function passwordHash(int $userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('password');
    }

    public function changePassword(int $userId, string $hash): void
    {
        DB::table('users')->where('id', $userId)->update(['password' => $hash, 'updated_at' => now()]);
    }

    public function delete(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            // `tokenable_type` = kelas model user (lama: App\Models\User).
            DB::table('personal_access_tokens')->where('tokenable_id', $userId)
                ->where('tokenable_type', 'like', '%User%')->delete();
            DB::table('users')->where('id', $userId)->delete();
        });
    }
}
