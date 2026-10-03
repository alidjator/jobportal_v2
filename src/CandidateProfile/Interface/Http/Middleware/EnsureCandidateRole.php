<?php

namespace Src\CandidateProfile\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Interface\Http\ApiResponse;

/**
 * Endpoint `/candidate/*` khusus role candidate. Aplikasi lama tidak memeriksa
 * (user company menyebabkan error 500 / baris kandidat liar); sekarang 403.
 */
class EnsureCandidateRole
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role !== 'candidate') {
            return ApiResponse::forbidden('Forbidden');
        }

        return $next($request);
    }
}
