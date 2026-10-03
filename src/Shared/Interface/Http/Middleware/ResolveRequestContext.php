<?php

namespace Src\Shared\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Application\RequestContext;

class ResolveRequestContext
{
    public function handle(Request $request, Closure $next)
    {
        $language = $request->header('X-Language')
            ?? $request->query('lang')
            ?? $this->primaryLanguage($request->header('Accept-Language'));

        $country = $request->header('selected_country')
            ?? $request->header('X-Country')
            ?? $request->query('country');

        $currency = $request->header('X-Currency') ?? $request->query('currency');

        $context = new RequestContext(
            language: $language !== null ? strtolower(trim((string) $language)) : null,
            countryId: is_numeric($country) ? (int) $country : null,
            currency: $currency !== null ? strtoupper(trim((string) $currency)) : null,
        );

        app()->instance(RequestContext::class, $context);

        if ($context->language !== null && preg_match('/^[a-z]{2,3}(-[a-z]{2})?$/', $context->language)) {
            app()->setLocale($context->language);
        }

        return $next($request);
    }

    private function primaryLanguage(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        return explode(',', explode(';', $header)[0])[0] ?: null;
    }
}
