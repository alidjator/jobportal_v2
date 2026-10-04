<?php

namespace Src\Shared\Interface\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Src\Shared\Application\RequestContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request language: the `Accept-Language` header (only `id` and `en` are supported), then the locale of the
 * authenticated account, then the default. The result sets the application locale and the request context, and is
 * echoed in `Content-Language`.
 */
final readonly class ResolveRequestLanguage
{
    /** The request attribute holding the resolved language; API resources read it to pick one language of a translated text. */
    public const string ATTRIBUTE = 'language';

    public function __construct(private Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $language = $this->fromHeader($request) ?? $this->fromAccount($request) ?? RequestContext::DEFAULT_LANGUAGE;

        $this->app->setLocale($language);
        $request->attributes->set(self::ATTRIBUTE, $language);
        $this->app->instance(RequestContext::class, new RequestContext($language));

        $response = $next($request);
        $response->headers->set('Content-Language', $language);
        // The body depends on the language header and, for a signed-in user, on the account's language: caches must keep them apart.
        $response->setVary(['Accept-Language', 'Authorization'], false);

        return $response;
    }

    private function fromHeader(Request $request): ?string
    {
        foreach ($request->getLanguages() as $tag) {
            $language = strtolower(explode('_', $tag)[0]);

            if (in_array($language, RequestContext::SUPPORTED_LANGUAGES, true)) {
                return $language;
            }
        }

        return null;
    }

    private function fromAccount(Request $request): ?string
    {
        $account = $request->user();
        $locale = $account instanceof HasLocalePreference ? $account->preferredLocale() : null;

        return in_array($locale, RequestContext::SUPPORTED_LANGUAGES, true) ? $locale : null;
    }
}
