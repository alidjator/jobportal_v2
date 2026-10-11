<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\TranslatedText;

/**
 * Replaces the subject and body of a template in one language after checking them against the template's rules, and audits it
 * (the entry names the template and language, not the text).
 */
final readonly class UpdateEmailTemplateOverride
{
    public function __construct(
        private EmailTemplateCatalog $catalog,
        private EmailTemplateOverrideRepository $overrides,
        private EmailTemplateViews $views,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-email-template` (with `errors` per field) or `unsupported-language`
     */
    public function execute(Actor $actor, string $key, string $locale, string $subject, string $body, ?string $ipAddress, ?string $userAgent): EmailTemplateView
    {
        $definition = $this->catalog->find($key) ?? throw EntityNotFound::of('Email template', $key);

        if (! in_array($locale, TranslatedText::LANGUAGES, true)) {
            throw new BusinessRuleViolation('The language must be one of: '.implode(', ', TranslatedText::LANGUAGES).'.', 'unsupported-language');
        }

        EmailTemplateRules::assertValid($definition, $subject, $body);

        $this->transactions->run(function () use ($actor, $key, $locale, $subject, $body, $ipAddress, $userAgent): void {
            $this->overrides->save($key, $locale, trim($subject), trim($body), $actor->id);
            $this->audit->record(new AuditEntry(
                AuditActorType::Admin, $actor->id, AuditAction::EMAIL_TEMPLATE_UPDATED, 'email_template', null,
                ['template' => $key, 'locale' => $locale], $ipAddress, $userAgent, $this->clock->now(),
            ));
        });

        return $this->views->one($key);
    }
}
