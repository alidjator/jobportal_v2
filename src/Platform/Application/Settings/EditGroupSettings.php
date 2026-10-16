<?php

namespace Src\Platform\Application\Settings;

use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Settings\GroupEditableSetting;
use Src\Shared\Application\Settings\GroupSettingChanged;
use Src\Shared\Application\Settings\GroupSettingsEditor;
use Src\Shared\Application\Settings\SettingDefinition;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Settings\SettingType;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Platform's side of the group settings page: the group-editable settings of a group, and the group admin's changes saved as the group's own values. */
final readonly class EditGroupSettings implements GroupSettingsEditor
{
    public function __construct(
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private StoredSettings $reader,
        private AuditTrail $audit,
        private EventDispatcher $events,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function editable(int $groupId): array
    {
        $settings = [];

        foreach ($this->catalog->all() as $definition) {
            if ($definition->groupEditable) {
                $settings[] = $this->present($definition, $groupId);
            }
        }

        return $settings;
    }

    public function update(int $groupId, int $actorUserId, array $values, ?string $ipAddress, ?string $userAgent): array
    {
        $normalized = [];
        $errors = [];

        foreach ($values as $key => $value) {
            $definition = $this->catalog->find((string) $key);

            if ($definition === null || ! $definition->groupEditable) {
                throw new BusinessRuleViolation("The setting {$key} cannot be changed by a group.", 'setting-not-editable', extensions: ['key' => (string) $key]);
            }

            try {
                $normalized[$definition->key] = $definition->normalize($value);
            } catch (BusinessRuleViolation $e) {
                $errors[$definition->key] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('Some values are not valid.', 'invalid-setting-value', extensions: ['errors' => $errors]);
        }

        $before = [];
        $after = [];
        $now = $this->clock->now();

        $this->transactions->run(function () use ($groupId, $actorUserId, $normalized, &$before, &$after, $ipAddress, $userAgent, $now): void {
            foreach ($normalized as $key => $value) {
                $current = $this->effective($this->catalog->find($key) ?? throw new \LogicException("Setting {$key} was just found."), $groupId);

                if ($current === $value) {
                    continue;
                }

                $this->store->setGroupValue($key, $groupId, $value);
                $before[$key] = $current;
                $after[$key] = $value;
            }

            if ($after !== []) {
                $this->audit->record(new AuditEntry(
                    AuditActorType::User, $actorUserId, AuditAction::GROUP_SETTINGS_UPDATED, 'organization_group', $groupId,
                    ['group_id' => $groupId, 'before' => $before, 'after' => $after], $ipAddress, $userAgent, $now,
                ));
            }
        });

        $this->events->dispatch(array_map(
            fn (string $key): GroupSettingChanged => new GroupSettingChanged($groupId, $key, $before[$key], $after[$key], $now),
            array_keys($after),
        ));

        return $this->editable($groupId);
    }

    private function present(SettingDefinition $definition, int $groupId): GroupEditableSetting
    {
        return new GroupEditableSetting(
            $definition->key,
            match ($definition->type) {
                SettingType::Integer => 'integer',
                SettingType::Boolean => 'boolean',
                SettingType::Text => 'text',
                SettingType::Choices => 'choices',
            },
            $definition->description, $this->effective($definition, $groupId), $this->global($definition), $definition->min, $definition->max, $definition->allowedValues,
        );
    }

    /** @return int|bool|string|list<string> the group's own value when it has one the setting still accepts, otherwise what applies without it */
    private function effective(SettingDefinition $definition, int $groupId): int|bool|string|array
    {
        $stored = $this->store->values()->groupValues[$definition->key] ?? [];

        if (array_key_exists($groupId, $stored)) {
            try {
                return $definition->normalize($stored[$groupId]);
            } catch (BusinessRuleViolation) {
                // The catalog no longer accepts it: it is ignored, as the reader ignores it.
            }
        }

        return $this->global($definition);
    }

    /** @return int|bool|string|list<string> */
    private function global(SettingDefinition $definition): int|bool|string|array
    {
        return match ($definition->type) {
            SettingType::Integer => $this->reader->integer($definition->key),
            SettingType::Boolean => $this->reader->boolean($definition->key),
            SettingType::Text => $this->reader->text($definition->key),
            SettingType::Choices => $this->reader->choices($definition->key),
        };
    }
}
