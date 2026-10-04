<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Platform\Application\Email\EmailTemplateOverride;
use Src\Platform\Application\Email\EmailTemplateOverrideRepository;
use stdClass;

final readonly class DatabaseEmailTemplateOverrideRepository implements EmailTemplateOverrideRepository
{
    private const string TABLE = 'platform_email_template_overrides';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(string $templateKey, string $locale): ?EmailTemplateOverride
    {
        $row = $this->connection->table(self::TABLE)->where('template_key', $templateKey)->where('locale', $locale)->first();

        return $row === null ? null : $this->toOverride($row);
    }

    public function forTemplate(string $templateKey): array
    {
        $overrides = [];

        foreach ($this->connection->table(self::TABLE)->where('template_key', $templateKey)->orderBy('locale')->get() as $row) {
            $overrides[(string) $row->locale] = $this->toOverride($row);
        }

        return $overrides;
    }

    public function save(string $templateKey, string $locale, string $subject, string $body, ?int $adminId): EmailTemplateOverride
    {
        $query = $this->connection->table(self::TABLE)->where('template_key', $templateKey)->where('locale', $locale);
        $values = ['subject' => $subject, 'body' => $body, 'updated_by_admin_id' => $adminId, 'updated_at' => now()];

        if ($query->exists()) {
            $query->update($values);
        } else {
            $this->connection->table(self::TABLE)->insert([...$values, 'template_key' => $templateKey, 'locale' => $locale, 'created_at' => now()]);
        }

        return $this->find($templateKey, $locale) ?? throw new \LogicException('The saved override cannot be read back.');
    }

    public function delete(string $templateKey, string $locale): bool
    {
        return $this->connection->table(self::TABLE)->where('template_key', $templateKey)->where('locale', $locale)->delete() > 0;
    }

    private function toOverride(stdClass $row): EmailTemplateOverride
    {
        return new EmailTemplateOverride(
            (string) $row->template_key,
            (string) $row->locale,
            (string) $row->subject,
            (string) $row->body,
            new DateTimeImmutable((string) $row->updated_at, new DateTimeZone('UTC')),
            $row->updated_by_admin_id === null ? null : (int) $row->updated_by_admin_id,
        );
    }
}
