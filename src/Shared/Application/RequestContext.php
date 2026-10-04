<?php

namespace Src\Shared\Application;

/**
 * Per-request preferences. The only one is the language (`id` or `en`).
 *
 * The API is stateless. The language is resolved from the `Accept-Language` header, then from the locale stored on
 * the authenticated account, then falls back to the default. Asynchronous work such as emails never uses a request
 * header: it uses the locale of the recipient's account.
 */
final readonly class RequestContext
{
    public const string DEFAULT_LANGUAGE = 'id';

    /** @var list<string> */
    public const array SUPPORTED_LANGUAGES = ['id', 'en'];

    public function __construct(
        public string $language = self::DEFAULT_LANGUAGE,
    ) {}
}
