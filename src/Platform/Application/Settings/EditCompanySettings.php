<?php

namespace Src\Platform\Application\Settings;

use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\CompanyEditableSetting;
use Src\Shared\Application\Settings\CompanySettingChanged;
use Src\Shared\Application\Settings\CompanySettingsEditor;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Settings\SettingType;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/** Platform's side of the company settings page: the editable settings of a company, and the company admin's changes saved as the company's own overrides. */
final readonly class EditCompanySettings implements CompanySettingsEditor
{
    public function __construct(
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private CompanySettingViews $views,
        private AuditTrail $audit,
        private EventDispatcher $events,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function editable(int $companyId): array
    {
        $settings = [];

        foreach ($this->catalog->all() as $definition) {
            if ($definition->companyEditable) {
                $settings[] = $this->present($this->views->one($companyId, $definition->key));
            }
        }

        return $settings;
    }

    public function update(int $companyId, int $actorUserId, array $values, ?string $ipAddress, ?string $userAgent): array
    {
        $normalized = [];
        $errors = [];

        foreach ($values as $key => $value) {
            $definition = $this->catalog->find((string) $key);

            if ($definition === null || ! $definition->companyEditable) {
                throw new BusinessRuleViolation('The setting :key cannot be changed by a company.', 'setting-not-editable', extensions: ['key' => (string) $key], parameters: ['key' => (string) $key]);
            }

            try {
                $normalized[$definition->key] = $definition->normalize($value);
            } catch (BusinessRuleViolation $e) {
                $errors[$definition->key] = new ErrorMessage($e->template(), $e->parameters());
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('Some values are not valid.', 'invalid-setting-value', extensions: ['errors' => $errors]);
        }

        $before = [];
        $after = [];
        $now = $this->clock->now();

        $this->transactions->run(function () use ($companyId, $actorUserId, $normalized, &$before, &$after, $ipAddress, $userAgent, $now): void {
            foreach ($normalized as $key => $value) {
                $current = $this->views->one($companyId, $key);

                if ($current->effectiveValue === $value) {
                    continue;
                }

                $this->store->setCompanyOverride($key, $companyId, $value, null);
                $before[$key] = $current->effectiveValue;
                $after[$key] = $value;
            }

            if ($after !== []) {
                $this->audit->record(new AuditEntry(
                    AuditActorType::User, $actorUserId, AuditAction::COMPANY_SETTINGS_UPDATED, 'company', $companyId,
                    ['before' => $before, 'after' => $after], $ipAddress, $userAgent, $now, $companyId,
                ));
            }
        });

        $this->events->dispatch(array_map(
            fn (string $key): CompanySettingChanged => new CompanySettingChanged($companyId, $key, $before[$key], $after[$key], $now),
            array_keys($after),
        ));

        return $this->editable($companyId);
    }

    private function present(CompanySettingView $view): CompanyEditableSetting
    {
        $definition = $view->definition;

        return new CompanyEditableSetting(
            $definition->key,
            match ($definition->type) {
                SettingType::Integer => 'integer',
                SettingType::Boolean => 'boolean',
                SettingType::Text => 'text',
                SettingType::Choices => 'choices',
            },
            $definition->description, $view->effectiveValue, $view->globalValue, $definition->min, $definition->max, $definition->allowedValues,
        );
    }
}
