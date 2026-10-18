<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\MessageDefaults;
use Src\Company\Domain\MessageDefaultsRepository;
use Src\Company\Domain\MessageLinkRule;
use Src\Shared\Application\Clock;

final readonly class DatabaseMessageDefaultsRepository implements MessageDefaultsRepository
{
    private const string TABLE = 'company_message_defaults';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $companyId): MessageDefaults
    {
        $row = $this->connection->table(self::TABLE)->where('company_id', $companyId)->first();

        return $row === null ? new MessageDefaults : new MessageDefaults($row->interview, $row->assessment, $row->offer, $row->applied, $row->hired);
    }

    public function save(int $companyId, MessageDefaults $defaults, int $changedByUserId): void
    {
        $now = $this->clock->now();

        $this->connection->table(self::TABLE)->upsert(
            [[...$defaults->toArray(), 'company_id' => $companyId, 'updated_by' => $changedByUserId, 'created_at' => $now, 'updated_at' => $now]],
            ['company_id'],
            [...MessageDefaults::SCENARIOS, 'updated_by', 'updated_at'],
        );
    }

    public function websiteDomain(int $companyId): ?string
    {
        return MessageLinkRule::domainOf($this->connection->table('company_companies')->where('id', $companyId)->value('website'));
    }
}
