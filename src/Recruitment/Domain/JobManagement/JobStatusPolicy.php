<?php

namespace Src\Recruitment\Domain\JobManagement;

/**
 * Perusahaan boleh menutup (`expired`) lowongannya kapan saja dan membukanya kembali
 * selama deadline belum lewat. Lowongan `pending` baru aktif lewat persetujuan admin
 * (kecuali auto-approve) dan status tidak bisa dikembalikan ke `pending` oleh perusahaan.
 */
final class JobStatusPolicy
{
    public static function canTransition(JobStatus $from, JobStatus $to, bool $autoApproved, bool $deadlinePassed): bool
    {
        if ($from === $to) {
            return true;
        }

        return match ($to) {
            JobStatus::Pending => false,
            JobStatus::Expired => true,
            JobStatus::Active => $from === JobStatus::Expired
                ? ! $deadlinePassed
                : $autoApproved && ! $deadlinePassed,
        };
    }
}
