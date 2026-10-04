<?php

namespace Src\Identity\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Identity\Domain\TermsAcceptanceRepository;

final readonly class DatabaseTermsAcceptanceRepository implements TermsAcceptanceRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function acceptedTypes(int $userId, array $documents): array
    {
        if ($documents === []) {
            return [];
        }

        return $this->connection->table('identity_terms_acceptances')
            ->where('user_id', $userId)
            ->where(function (Builder $query) use ($documents): void {
                foreach ($documents as $type => $version) {
                    $query->orWhere(fn (Builder $pair) => $pair->where('document_type', $type)->where('document_version', $version));
                }
            })
            ->distinct()
            ->pluck('document_type')
            ->map(fn ($type): string => (string) $type)
            ->values()
            ->all();
    }

    public function record(int $userId, string $type, int $version, string $locale, ?string $ipAddress, DateTimeImmutable $acceptedAt): void
    {
        $this->connection->table('identity_terms_acceptances')->insertOrIgnore([
            'user_id' => $userId,
            'document_type' => $type,
            'document_version' => $version,
            'locale' => $locale,
            'accepted_at' => $acceptedAt->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
        ]);
    }
}
