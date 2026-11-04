<?php

namespace Src\Assessment\Interface\Http;

use Illuminate\Http\Response;
use Src\Assessment\Application\Authoring\QuestionImageFile;

/** A question picture as an image that browsers may not reinterpret; it has no public URL and is never cached by a shared cache. */
final class QuestionImageResponse
{
    public static function of(QuestionImageFile $file): Response
    {
        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
