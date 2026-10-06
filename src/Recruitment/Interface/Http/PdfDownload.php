<?php

namespace Src\Recruitment\Interface\Http;

use Illuminate\Http\Response;
use Src\Recruitment\Application\ApplicationFile;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The download of an application's resume copy: named as the applicant named it with `.pdf` added; the ASCII fallback is for clients that cannot read the encoded name. */
final class PdfDownload
{
    public static function of(ApplicationFile $file): Response
    {
        $name = str_ends_with(strtolower($file->name), '.pdf') ? $file->name : $file->name.'.pdf';
        $fallback = str_replace(['%', '/', '\\', '"'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $name));

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
