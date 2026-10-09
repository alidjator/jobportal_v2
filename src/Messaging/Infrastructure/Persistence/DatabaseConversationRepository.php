<?php

namespace Src\Messaging\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Messaging\Domain\Conversation;
use Src\Messaging\Domain\ConversationRepository;
use stdClass;

final readonly class DatabaseConversationRepository implements ConversationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function findById(int $conversationId, bool $forUpdate = false): ?Conversation
    {
        return $this->first($this->connection->table('messaging_conversations')->where('id', $conversationId), $forUpdate);
    }

    public function findByPair(int $companyId, int $jobSeekerUserId, bool $forUpdate = false): ?Conversation
    {
        return $this->first(
            $this->connection->table('messaging_conversations')->where('company_id', $companyId)->where('job_seeker_user_id', $jobSeekerUserId),
            $forUpdate,
        );
    }

    public function addOrFind(Conversation $conversation): Conversation
    {
        $at = $this->format($conversation->createdAt);

        $this->connection->table('messaging_conversations')->insertOrIgnore([
            'company_id' => $conversation->companyId,
            'job_seeker_user_id' => $conversation->jobSeekerUserId,
            'started_by_user_id' => $conversation->startedByUserId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return $this->findByPair($conversation->companyId, $conversation->jobSeekerUserId) ?? $conversation;
    }

    public function update(Conversation $conversation): void
    {
        $this->connection->table('messaging_conversations')->where('id', $conversation->id)->update([
            'closed_at' => $conversation->closedAt === null ? null : $this->format($conversation->closedAt),
            'closed_by_user_id' => $conversation->closedByUserId,
            'last_message_at' => $conversation->lastMessageAt === null ? null : $this->format($conversation->lastMessageAt),
            'updated_at' => $this->format(new DateTimeImmutable),
        ]);
    }

    private function first(Builder $query, bool $forUpdate): ?Conversation
    {
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toConversation($row);
    }

    private function toConversation(stdClass $row): Conversation
    {
        return new Conversation(
            (int) $row->company_id,
            (int) $row->job_seeker_user_id,
            (int) $row->started_by_user_id,
            $this->time((string) $row->created_at),
            $row->closed_at === null ? null : $this->time((string) $row->closed_at),
            $row->closed_by_user_id === null ? null : (int) $row->closed_by_user_id,
            $row->last_message_at === null ? null : $this->time((string) $row->last_message_at),
            (int) $row->id,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
