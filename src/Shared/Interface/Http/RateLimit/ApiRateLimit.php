<?php

namespace Src\Shared\Interface\Http\RateLimit;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

/**
 * The baseline limit of the whole `api` group: per account (guard and id) when the request carries a valid token, per IP address otherwise. The named
 * limiters of the contexts stay on their routes and apply on top of it. The numbers come from `config/security.php`.
 */
final class ApiRateLimit
{
    public const string NAME = 'api';

    private const array GUARDS = ['user', 'admin'];

    public static function limit(Request $request): Limit
    {
        foreach (self::GUARDS as $guard) {
            $account = $request->bearerToken() === null ? null : $request->user($guard);

            if ($account !== null) {
                return Limit::perMinute((int) config('security.rate_limit.user'))->by('api|'.$guard.'|'.$account->getAuthIdentifier());
            }
        }

        return Limit::perMinute((int) config('security.rate_limit.guest'))->by('api|ip|'.$request->ip());
    }
}
