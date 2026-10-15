<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Http\JsonResponse;

/** The answer at `/`: a fixed, minimal sign that the API is up. It deliberately lists no endpoints, build number or server details. */
final class ApiRootController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['name' => 'ONESIS API', 'version' => 'v1', 'status' => 'ok']);
    }
}
