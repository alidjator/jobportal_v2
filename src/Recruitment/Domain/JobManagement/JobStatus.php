<?php

namespace Src\Recruitment\Domain\JobManagement;

/** Nilai enum kolom `jobs.status`. */
enum JobStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /** Status hasil edit di tempat: lowongan aktif kedaluwarsa bila deadline lewat. */
    public static function forDeadline(bool $deadlineReached): self
    {
        return $deadlineReached ? self::Active : self::Expired;
    }
}
