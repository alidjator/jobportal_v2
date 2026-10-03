<?php

namespace Src\Identity\Domain;

interface AccessTokens
{
    /** Membuat token akses baru (plain text) bernama `job-pilot`. */
    public function issue(int $userId): string;

    public function revokeAll(int $userId): void;
}
