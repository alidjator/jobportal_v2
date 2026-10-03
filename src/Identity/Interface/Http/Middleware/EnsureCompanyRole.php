<?php

namespace Src\Identity\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Interface\Http\ApiResponse;

/**
 * Alias `api_company`. Kompatibel dengan aplikasi lama: role selain company
 * mendapat 401 `{error}` (kandidat untuk diubah menjadi 403 di versi berikutnya).
 */
class EnsureCompanyRole
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role === 'company') {
            return $next($request);
        }

        return ApiResponse::unauthenticated('Unathenticated');
    }
}
