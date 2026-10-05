<?php

namespace Src\Platform\Application\Settings;

use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Removes a company's own value so the global one applies again, and audits it. A company without an override changes nothing and is not audited. */
final readonly class ResetCompanySetting
{
    public function __construct(
        private CompanyDirectory $companies,
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private CompanySettingViews $views,
        private RecordAuditEntry $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound when the company or the setting does not exist
     * @throws BusinessRuleViolation `setting-not-overridable`
     */
    public function execute(Actor $actor, int $companyId, string $key, ?string $ipAddress, ?string $userAgent): CompanySettingView
    {
        if (! $this->companies->exists($companyId)) {
            throw EntityNotFound::of('Company', $companyId);
        }

        $definition = $this->catalog->find($key) ?? throw EntityNotFound::of('Setting', $key);

        if (! $definition->overridable) {
            throw new BusinessRuleViolation('This setting cannot be overridden per company.', 'setting-not-overridable');
        }

        $current = $this->views->one($companyId, $key);

        if (! $current->hasOverride) {
            return $current;
        }

        $this->transactions->run(function () use ($actor, $companyId, $key, $current, $ipAddress, $userAgent): void {
            $this->store->removeCompanyOverride($key, $companyId);
            $this->audit->execute(new AuditEntry(
                AuditActorType::Admin, $actor->id, AuditAction::SETTING_RESET, 'company', $companyId,
                ['key' => $key, 'old' => $current->override], $ipAddress, $userAgent, $this->clock->now(),
            ));
        });

        return $this->views->one($companyId, $key);
    }
}
