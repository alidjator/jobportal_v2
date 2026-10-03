<?php

namespace Src\Content\Application;

use Src\Content\Domain\LanguageCodes;
use Src\Shared\Application\RequestContext;

/** Bahasa aktif untuk konten CMS: bahasa request (jika terdaftar), lalu bahasa default. */
final class ContentLanguage
{
    public function __construct(
        private RequestContext $context,
        private LanguageCodes $languages,
    ) {}

    public function default(): string
    {
        return (string) config('jobportal.default_language');
    }

    public function current(): string
    {
        $requested = $this->context->language;

        return $requested && $this->languages->exists($requested) ? $requested : $this->default();
    }

    /** @return array<int, string> */
    public function priority(): array
    {
        return array_values(array_unique([$this->current(), $this->default()]));
    }
}
