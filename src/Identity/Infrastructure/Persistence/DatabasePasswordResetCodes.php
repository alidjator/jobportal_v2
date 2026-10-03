<?php

namespace Src\Identity\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Identity\Domain\PasswordResetCodes;
use Src\Identity\Domain\ResetCodeStatus;

/** Tabel `verification_codes` (type `reset_password`), sama dengan model VerificationCode lama. */
class DatabasePasswordResetCodes implements PasswordResetCodes
{
    private const TYPE = 'reset_password';

    public function issue(int $userId): string
    {
        $code = (string) random_int(100000, 999999);
        $now = now();

        DB::transaction(function () use ($userId, $code, $now) {
            $this->query()->where('user_id', $userId)->delete();

            $this->query()->insert([
                'user_id' => $userId,
                'code' => $code,
                'type' => self::TYPE,
                'expire_at' => $now->copy()->addMinutes((int) config('identity.reset_code_ttl_minutes', 60)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $code;
    }

    public function consume(int $userId, string $code): ResetCodeStatus
    {
        $row = $this->query()->where('user_id', $userId)->where('code', $code)->first();

        if ($row === null) {
            return ResetCodeStatus::Invalid;
        }

        // Kode lama tanpa expire_at dianggap kedaluwarsa.
        if ($row->expire_at === null || now()->isAfter($row->expire_at)) {
            return ResetCodeStatus::Expired;
        }

        // Hapus atomik: hanya satu request yang berhasil memakai kode.
        return $this->query()->where('id', $row->id)->delete() === 1
            ? ResetCodeStatus::Valid
            : ResetCodeStatus::Invalid;
    }

    private function query(): Builder
    {
        return DB::table('verification_codes')->where('type', self::TYPE);
    }
}
