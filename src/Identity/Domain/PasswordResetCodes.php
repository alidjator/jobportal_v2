<?php

namespace Src\Identity\Domain;

interface PasswordResetCodes
{
    /** Membuat kode 6 digit baru (kode lama user dihapus) dan mengembalikannya. */
    public function issue(int $userId): string;

    /** Memeriksa kode; bila valid kode langsung dihabiskan (sekali pakai). */
    public function consume(int $userId, string $code): ResetCodeStatus;
}
