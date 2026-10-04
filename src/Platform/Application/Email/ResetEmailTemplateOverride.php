<?php

namespace Src\Platform\Application\Email;

use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\TranslatedText;

/** Restores the default subject and body of a template in one language by removing the override. When there was no override nothing changes and nothing is audited. */
final readonly class ResetEmailTemplateOverride
{
    public function __construct(
        private EmailTemplateCatalog $catalog,
        private EmailTemplateOverrideRepository $overrides,
        private EmailTemplateViews $views,
        private RecordAuditEntry $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `unsupported-language`
     */
    public function execute(Actor $actor, string $key, string $locale, ?string $ipAddress, ?string $userAgent): EmailTemplateView
    {
        $this->catalog->find($key) ?? throw EntityNotFound::of('Email template', $key);

        if (! in_array($locale, TranslatedText::LANGUAGES, true)) {
            throw new BusinessRuleViolation('The language must be one of: '.implode(', ', TranslatedText::LANGUAGES).'.', 'unsupported-language');
        }

        $this->transactions->run(function () use ($actor, $key, $locale, $ipAddress, $userAgent): void {
            if ($this->overrides->delete($key, $locale)) {
                $this->audit->execute(new AuditEntry(
                    AuditActorType::Admin, $actor->id, AuditAction::EMAIL_TEMPLATE_RESET, 'email_template', null,
                    ['template' => $key, 'locale' => $locale], $ipAddress, $userAgent, $this->clock->now(),
                ));
            }
        });

        return $this->views->one($key);
    }
}
