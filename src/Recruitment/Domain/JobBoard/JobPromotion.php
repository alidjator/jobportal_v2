<?php

namespace Src\Recruitment\Domain\JobBoard;

use Carbon\Carbon;

/**
 * Aturan "featured"/"highlight" lowongan: flag aktif hanya selama masa promosi
 * (`*_until`) belum lewat, bila pengaturan hari promosi > 0.
 * (Aplikasi lama juga menulis `0` ke DB saat kedaluwarsa; di sini hanya dihitung.)
 */
final class JobPromotion
{
    public static function isActive(mixed $flag, ?string $until, int $configuredDays): bool
    {
        if (! $flag) {
            return false;
        }

        if ($configuredDays > 0 && $until) {
            return Carbon::parse($until)->isFuture();
        }

        return true;
    }
}
