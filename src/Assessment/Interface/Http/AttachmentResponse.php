<?php

namespace Src\Assessment\Interface\Http;

use Illuminate\Http\Response;
use Src\Assessment\Application\Delivery\AttachmentFile;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The file of an invitation as a download that browsers may not render or reinterpret; it has no public URL. */
final class AttachmentResponse
{
    public static function of(AttachmentFile $file): Response
    {
        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->name, 'attachment.'.pathinfo($file->name, PATHINFO_EXTENSION)),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
