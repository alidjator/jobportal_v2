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
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Puts a setting back to its default by removing the stored value, and audits it. A setting that was already at its default changes nothing and is not audited. */
final readonly class ResetSetting
{
    public function __construct(
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private SettingViews $views,
        private RecordAuditEntry $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws EntityNotFound */
    public function execute(Actor $actor, string $key, ?string $ipAddress, ?string $userAgent): SettingView
    {
        $this->catalog->find($key) ?? throw EntityNotFound::of('Setting', $key);
        $current = $this->views->one($key);

        if ($current->isDefault) {
            return $current;
        }

        $this->transactions->run(function () use ($actor, $key, $current, $ipAddress, $userAgent): void {
            $this->store->resetGlobal($key);
            $this->audit->execute(new AuditEntry(
                AuditActorType::Admin, $actor->id, AuditAction::SETTING_RESET, 'setting', null,
                ['key' => $key, 'old' => $current->value], $ipAddress, $userAgent, $this->clock->now(),
            ));
        });

        return $this->views->one($key);
    }
}
