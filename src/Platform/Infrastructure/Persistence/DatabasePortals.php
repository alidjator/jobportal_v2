<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Src\Platform\Application\Portal\PortalDraft;
use Src\Platform\Application\Portal\PortalIdentity;
use Src\Platform\Application\Portal\Portals;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;

final class DatabasePortals implements Portals
{
    private readonly Connection $connection;

    public function __construct(DatabaseManager $database)
    {
        $this->connection = $database->connection();
    }

    public function identity(): ?PortalIdentity
    {
        $row = $this->connection->table('platform_portal_identity')->orderBy('id')->first(['mode', 'scope', 'company_ids']);

        if ($row === null) {
            return null;
        }

        return PortalIdentity::fromArray([
            'mode' => $row->mode,
            'scope' => $row->scope,
            'company_ids' => json_decode((string) $row->company_ids, true, 512, JSON_THROW_ON_ERROR),
        ]);
    }

    public function saveIdentity(PortalIdentity $identity, ?int $adminId, DateTimeImmutable $at): void
    {
        $values = [
            'mode' => $identity->mode,
            'scope' => $identity->scope,
            'company_ids' => json_encode($identity->companyIds, JSON_THROW_ON_ERROR),
            'updated_by_admin_id' => $adminId,
            'updated_at' => $at,
        ];
        $id = $this->connection->table('platform_portal_identity')->orderBy('id')->value('id');

        if ($id === null) {
            $this->connection->table('platform_portal_identity')->insert($values);
        } else {
            $this->connection->table('platform_portal_identity')->where('id', $id)->update($values);
        }
    }

    public function alertSignature(): ?string
    {
        $value = $this->connection->table('platform_portal_identity')->orderBy('id')->value('alert_signature');

        return $value === null ? null : (string) $value;
    }

    public function rememberAlertSignature(?string $signature): void
    {
        $this->connection->table('platform_portal_identity')->update(['alert_signature' => $signature]);
    }

    public function brand(PortalOwner $owner): ?PortalBrandValues
    {
        $row = $this->connection->table('platform_portal_brands')
            ->where('owner_type', $owner->type->value)->where('owner_id', $owner->id)
            ->first(['name', 'tagline_id', 'tagline_en', 'welcome_id', 'welcome_en', 'preset', 'theme_mode', 'contrast', 'width', 'header_color']);

        return $row === null ? null : PortalBrandValues::fromArray((array) $row);
    }

    public function saveBrand(PortalOwner $owner, PortalBrandValues $brand, ?int $userId, DateTimeImmutable $at): void
    {
        $key = ['owner_type' => $owner->type->value, 'owner_id' => $owner->id];

        $this->connection->table('platform_portal_brands')->updateOrInsert($key, [...$brand->toArray(), 'updated_by_user_id' => $userId, 'updated_at' => $at]);
    }

    public function draft(PortalOwner $owner): ?PortalDraft
    {
        $row = $this->connection->table('platform_portal_drafts')
            ->where('owner_type', $owner->type->value)->where('owner_id', $owner->id)
            ->first(['payload', 'updated_at']);

        if ($row === null) {
            return null;
        }

        return new PortalDraft(
            json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR),
            new DateTimeImmutable((string) $row->updated_at, new DateTimeZone('UTC')),
        );
    }

    public function saveDraft(PortalOwner $owner, array $payload, ?int $updatedBy, DateTimeImmutable $at): void
    {
        $key = ['owner_type' => $owner->type->value, 'owner_id' => $owner->id];

        $this->connection->table('platform_portal_drafts')->updateOrInsert($key, [
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_by' => $updatedBy,
            'updated_at' => $at,
        ]);
    }

    public function deleteDraft(PortalOwner $owner): void
    {
        $this->connection->table('platform_portal_drafts')->where('owner_type', $owner->type->value)->where('owner_id', $owner->id)->delete();
    }
}
