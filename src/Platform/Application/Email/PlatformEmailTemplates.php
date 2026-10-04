<?php

namespace Src\Platform\Application\Email;

use InvalidArgumentException;
use Src\Shared\Application\Email\EmailPlaceholders;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Email\RenderedEmail;
use Src\Shared\Application\RequestContext;
use Src\Shared\Domain\TranslatedText;

/** Renders a template in a language from the admin's override or, failing that, the default of the catalog. */
final readonly class PlatformEmailTemplates implements EmailTemplates
{
    public function __construct(
        private EmailTemplateCatalog $catalog,
        private EmailTemplateOverrideRepository $overrides,
        private EmailBodyRenderer $renderer,
    ) {}

    public function render(string $key, string $locale, array $variables): RenderedEmail
    {
        $definition = $this->catalog->find($key) ?? throw new InvalidArgumentException("Email template {$key} is not registered.");
        $language = in_array($locale, TranslatedText::LANGUAGES, true) ? $locale : RequestContext::DEFAULT_LANGUAGE;
        $override = $this->overrides->find($key, $language);

        $known = array_intersect_key($variables, array_flip($definition->variableNames()));
        $markdown = EmailPlaceholders::intoBody($override->body ?? $definition->defaultBody->toArray()[$language], $known);

        return new RenderedEmail(
            EmailPlaceholders::intoSubject($override->subject ?? $definition->defaultSubject->toArray()[$language], $known),
            $this->renderer->html($markdown),
            $this->renderer->text($markdown),
        );
    }
}
