<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Platform\Application\Portal\GetPortal;
use Src\Platform\Interface\Http\Resources\PortalResource;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\RequestLanguage;
use Symfony\Component\HttpFoundation\Response;

/** The public portal: who the career site is for and how it looks. A minute of public cache; a preview (header `X-Portal-Preview`) is never cached. */
final class PortalController extends ApiController
{
    public const string PREVIEW_HEADER = 'X-Portal-Preview';

    public function show(Request $request, GetPortal $get): Response
    {
        $token = $request->header(self::PREVIEW_HEADER);
        $response = (new PortalResource($get->execute(RequestLanguage::of($request), is_string($token) && $token !== '' ? $token : null)))->toResponse($request);

        $response->setVary(['Accept-Language', self::PREVIEW_HEADER], false);

        if (is_string($token) && $token !== '') {
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        $response->setCache(['public' => true, 'max_age' => 60, 'etag' => md5((string) $response->getContent())]);
        $response->isNotModified($request);

        return $response;
    }
}
