<?php

namespace Src\Platform\Application\Settings;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Gives one company its own value for an overridable setting, after checking the value against the catalog, and audits it with the old
 * and new value. Setting the value the company already has changes nothing and is not audited; an explicit value is stored even when it
 * equals the global one.
 */
final readonly class SetCompanySetting
{
    public function __construct(
        private CompanyDirectory $companies,
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private CompanySettingViews $views,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound when the company or the setting does not exist
     * @throws BusinessRuleViolation `setting-not-overridable` or `invalid-setting-value`
     */
    public function execute(Actor $actor, int $companyId, string $key, mixed $value, ?string $ipAddress, ?string $userAgent): CompanySettingView
    {
        if (! $this->companies->exists($companyId)) {
            throw EntityNotFound::of('Company', $companyId);
        }

        $definition = $this->catalog->find($key) ?? throw EntityNotFound::of('Setting', $key);

        if (! $definition->overridable) {
            throw new BusinessRuleViolation('This setting cannot be overridden per company.', 'setting-not-overridable');
        }

        $new = $definition->normalize($value);
        $current = $this->views->one($companyId, $key);

        if ($current->hasOverride && $current->override === $new) {
            return $current;
        }

        $this->transactions->run(function () use ($actor, $companyId, $key, $new, $current, $ipAddress, $userAgent): void {
            $this->store->setCompanyOverride($key, $companyId, $new, $actor->id);
            $this->audit->record(new AuditEntry(
                AuditActorType::Admin, $actor->id, AuditAction::SETTING_UPDATED, 'company', $companyId,
                ['key' => $key, 'old' => $current->effectiveValue, 'new' => $new], $ipAddress, $userAgent, $this->clock->now(),
            ));
        });

        return $this->views->one($companyId, $key);
    }
}
