<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Platform\Application\Settings\StoredSetting;
use Src\Platform\Application\Settings\StoredValues;

/**
 * Reads every stored value in two queries and keeps them in the application cache for an hour; each write drops the cache once its
 * transaction has committed, so a reader can never cache the old values after the new ones are visible. Within a request the values are
 * also remembered in memory. With several servers a shared cache store is needed for changes to show everywhere at once.
 */
final class DatabaseSettingsStore implements SettingsStore
{
    private const string CACHE_KEY = 'platform:settings';

    private const int CACHE_SECONDS = 3600;

    private const string COMPANY_SCOPE = 'company';

    private const string GROUP_SCOPE = 'group';

    private ?StoredValues $memo = null;

    private readonly Connection $connection;

    public function __construct(
        DatabaseManager $database,
        private readonly Cache $cache,
    ) {
        $this->connection = $database->connection();
    }

    public function values(): StoredValues
    {
        return $this->memo ??= $this->fromRaw($this->cache->remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->load()));
    }

    public function setGlobal(string $key, mixed $value, ?int $adminId): void
    {
        $this->upsert('platform_settings', ['key' => $key], $value, $adminId);

        $this->invalidateAfterCommit();
    }

    public function resetGlobal(string $key): bool
    {
        $deleted = $this->connection->table('platform_settings')->where('key', $key)->delete() > 0;

        $this->invalidateAfterCommit();

        return $deleted;
    }

    public function setCompanyOverride(string $key, int $companyId, mixed $value, ?int $adminId): void
    {
        $this->upsert('platform_setting_overrides', ['key' => $key, 'scope_type' => self::COMPANY_SCOPE, 'scope_id' => $companyId], $value, $adminId);

        $this->invalidateAfterCommit();
    }

    public function removeCompanyOverride(string $key, int $companyId): bool
    {
        $deleted = $this->connection->table('platform_setting_overrides')
            ->where('key', $key)->where('scope_type', self::COMPANY_SCOPE)->where('scope_id', $companyId)
            ->delete() > 0;

        $this->invalidateAfterCommit();

        return $deleted;
    }

    public function setGroupValue(string $key, int $groupId, mixed $value): void
    {
        $this->upsert('platform_setting_overrides', ['key' => $key, 'scope_type' => self::GROUP_SCOPE, 'scope_id' => $groupId], $value, null);

        $this->invalidateAfterCommit();
    }

    public function removeGroupValue(string $key, int $groupId): bool
    {
        $deleted = $this->connection->table('platform_setting_overrides')
            ->where('key', $key)->where('scope_type', self::GROUP_SCOPE)->where('scope_id', $groupId)
            ->delete() > 0;

        $this->invalidateAfterCommit();

        return $deleted;
    }

    /** @param  array<string, int|string>  $identity */
    private function upsert(string $table, array $identity, mixed $value, ?int $adminId): void
    {
        $query = $this->connection->table($table)->where($identity);
        $values = ['value' => $this->encode($value), 'updated_by_admin_id' => $adminId, 'updated_at' => now()];

        if ($query->exists()) {
            $query->update($values);

            return;
        }

        $this->connection->table($table)->insert([...$identity, ...$values, 'created_at' => now()]);
    }

    private function invalidateAfterCommit(): void
    {
        $this->memo = null;
        $this->connection->afterCommit(function (): void {
            $this->cache->forget(self::CACHE_KEY);
            $this->memo = null;
        });
    }

    /** @return array{globals: array<string, array{value: mixed, updated_at: string, admin: int|null}>, overrides: array<string, array<int, mixed>>, groups: array<string, array<int, mixed>>} */
    private function load(): array
    {
        $globals = [];

        foreach ($this->connection->table('platform_settings')->get(['key', 'value', 'updated_at', 'updated_by_admin_id']) as $row) {
            $globals[(string) $row->key] = [
                'value' => json_decode((string) $row->value, true, 512, JSON_THROW_ON_ERROR),
                'updated_at' => (string) $row->updated_at,
                'admin' => $row->updated_by_admin_id === null ? null : (int) $row->updated_by_admin_id,
            ];
        }

        $overrides = [];
        $groupValues = [];

        foreach ($this->connection->table('platform_setting_overrides')->get(['key', 'scope_type', 'scope_id', 'value']) as $row) {
            $value = json_decode((string) $row->value, true, 512, JSON_THROW_ON_ERROR);

            if ($row->scope_type === self::GROUP_SCOPE) {
                $groupValues[(string) $row->key][(int) $row->scope_id] = $value;
            } else {
                $overrides[(string) $row->key][(int) $row->scope_id] = $value;
            }
        }

        return ['globals' => $globals, 'overrides' => $overrides, 'groups' => $groupValues];
    }

    /** @param  array{globals: array<string, array{value: mixed, updated_at: string, admin: int|null}>, overrides: array<string, array<int, mixed>>, groups?: array<string, array<int, mixed>>}  $raw */
    private function fromRaw(array $raw): StoredValues
    {
        $globals = [];

        foreach ($raw['globals'] as $key => $stored) {
            $globals[$key] = new StoredSetting($stored['value'], new DateTimeImmutable($stored['updated_at'], new DateTimeZone('UTC')), $stored['admin']);
        }

        return new StoredValues($globals, $raw['overrides'], $raw['groups'] ?? []);
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
