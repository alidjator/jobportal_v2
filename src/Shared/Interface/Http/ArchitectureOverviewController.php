<?php

namespace Src\Shared\Interface\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The architecture overview page (`docs/design/architecture-overview.html`) at `/docs/architecture`. It is served under the same flag as the API document
 * (`API_DOCS_ENABLED`), so production answers 404. The page is one self-contained file with inline styles and one inline script, which its policy allows;
 * nothing is loaded from elsewhere.
 */
final class ArchitectureOverviewController
{
    public const string PATH = 'docs/design/architecture-overview.html';

    public function __invoke(): Response
    {
        $path = base_path(self::PATH);

        if (! config('scramble.enabled') || ! is_file($path)) {
            throw new NotFoundHttpException;
        }

        return new Response((string) file_get_contents($path), Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src data:; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
