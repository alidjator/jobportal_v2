<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Application\Email\EmailPlaceholders;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Email\RenderedEmail;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\TranslatedText;

/**
 * Shows how an email will look with sample values, without saving or sending anything. A draft subject or body (not yet saved)
 * is checked with the same rules as saving; what is not given falls back to the override in force, then to the default.
 */
final readonly class PreviewEmailTemplate
{
    public function __construct(
        private EmailTemplateCatalog $catalog,
        private EmailTemplateOverrideRepository $overrides,
        private EmailBodyRenderer $renderer,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-email-template` for a draft that breaks the rules, `unsupported-language`
     */
    public function execute(string $key, string $locale, ?string $draftSubject, ?string $draftBody): RenderedEmail
    {
        $definition = $this->catalog->find($key) ?? throw EntityNotFound::of('Email template', $key);

        if (! in_array($locale, TranslatedText::LANGUAGES, true)) {
            throw new BusinessRuleViolation('The language must be one of: :languages.', 'unsupported-language', parameters: ['languages' => implode(', ', TranslatedText::LANGUAGES)]);
        }

        $override = $this->overrides->find($key, $locale);
        $subject = $draftSubject ?? $override->subject ?? $definition->defaultSubject->toArray()[$locale];
        $body = $draftBody ?? $override->body ?? $definition->defaultBody->toArray()[$locale];

        if ($draftSubject !== null || $draftBody !== null) {
            EmailTemplateRules::assertValid($definition, $subject, $body);
        }

        $samples = [];

        foreach ($definition->variables as $variable) {
            $samples[$variable->name] = $variable->sample;
        }

        $markdown = EmailPlaceholders::intoBody(trim($body), $samples);

        return new RenderedEmail(
            EmailPlaceholders::intoSubject(trim($subject), $samples),
            $this->renderer->html($markdown),
            $this->renderer->text($markdown),
        );
    }
}
