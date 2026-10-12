<?php

namespace Src\Recruitment\Interface\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only the video server and its converter reach the internal webhooks: they send the shared secret as a bearer token or as the password of basic
 * authentication (the event handler of Janus can only do the latter). With no secret configured every call is refused.
 */
final readonly class VerifyJanusWebhook
{
    public const string ERROR_CODE = 'invalid-webhook-credentials';

    public function __construct(private Repository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->config->get('services.janus.webhook_secret');
        $given = $request->bearerToken() ?? $request->getPassword();

        if (! is_string($secret) || $secret === '' || ! is_string($given) || ! hash_equals($secret, $given)) {
            throw new AuthenticationFailed('The webhook credentials are wrong.', self::ERROR_CODE);
        }

        return $next($request);
    }
}
