<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Http\Request;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\Middleware\ResolveRequestLanguage;

/** Reads the language the middleware resolved for this request. */
final class RequestLanguage
{
    public static function of(Request $request): string
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE);

        return is_string($language) ? $language : RequestContext::DEFAULT_LANGUAGE;
    }
}
