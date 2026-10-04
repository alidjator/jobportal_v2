<?php

namespace Src\Platform\Application\Settings;

use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Sets the global value of a setting after checking it against the catalog, and audits the change (old and new value). Setting the value already in force changes nothing and is not audited. */
final readonly class UpdateSetting
{
    public function __construct(
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private SettingViews $views,
        private RecordAuditEntry $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-setting-value`
     */
    public function execute(Actor $actor, string $key, mixed $value, ?string $ipAddress, ?string $userAgent): SettingView
    {
        $definition = $this->catalog->find($key) ?? throw EntityNotFound::of('Setting', $key);
        $new = $definition->normalize($value);
        $current = $this->views->one($key);

        if ($current->value === $new && ! $current->isDefault) {
            return $current;
        }

        $this->transactions->run(function () use ($actor, $key, $new, $current, $ipAddress, $userAgent): void {
            $this->store->setGlobal($key, $new, $actor->id);
            $this->audit->execute(new AuditEntry(
                AuditActorType::Admin, $actor->id, AuditAction::SETTING_UPDATED, 'setting', null,
                ['key' => $key, 'old' => $current->value, 'new' => $new], $ipAddress, $userAgent, $this->clock->now(),
            ));
        });

        return $this->views->one($key);
    }
}
