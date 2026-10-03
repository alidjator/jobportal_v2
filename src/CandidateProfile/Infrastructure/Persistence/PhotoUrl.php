<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

/** Padanan accessor `Candidate::photo` / `User::image_url` aplikasi lama. */
final class PhotoUrl
{
    public static function of(?string $path): string
    {
        return asset($path ?: 'backend/image/default.png');
    }
}
