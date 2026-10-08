<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\OfferStatus;
use stdClass;

final readonly class DatabaseOfferRepository implements OfferRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function findById(int $offerId): ?Offer
    {
        $row = $this->connection->table('recruitment_offers')->where('id', $offerId)->first();

        return $row === null ? null : $this->toOffer($row);
    }

    public function active(int $applicationId, bool $forUpdate = false): ?Offer
    {
        $query = $this->connection->table('recruitment_offers')->where('active_key', $applicationId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toOffer($row);
    }

    public function forApplication(int $applicationId): array
    {
        return $this->connection->table('recruitment_offers')
            ->where('application_id', $applicationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (stdClass $row): Offer => $this->toOffer($row))
            ->all();
    }

    public function add(Offer $offer): Offer
    {
        $at = $this->format($offer->createdAt);

        $id = (int) $this->connection->table('recruitment_offers')->insertGetId([
            'application_id' => $offer->applicationId,
            'position' => $offer->position,
            'salary' => $offer->salary,
            'start_date' => $offer->startDate->format('Y-m-d'),
            'note' => $offer->note,
            'expires_at' => $this->format($offer->expiresAt),
            'created_by' => $offer->createdBy,
            'status' => $offer->status->value,
            'active_key' => $offer->status->isActive() ? $offer->applicationId : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return new Offer(
            $offer->applicationId,
            $offer->position,
            $offer->salary,
            $offer->startDate,
            $offer->note,
            $offer->expiresAt,
            $offer->createdBy,
            $offer->status,
            null,
            null,
            $offer->createdAt,
            null,
            $id,
        );
    }

    public function update(Offer $offer): void
    {
        $this->connection->table('recruitment_offers')->where('id', $offer->id)->update([
            'status' => $offer->status->value,
            'answer_note' => $offer->answerNote,
            'answered_at' => $offer->answeredAt === null ? null : $this->format($offer->answeredAt),
            'active_key' => $offer->status->isActive() ? $offer->applicationId : null,
            'reminded_at' => $offer->remindedAt === null ? null : $this->format($offer->remindedAt),
            'updated_at' => $this->format(new DateTimeImmutable),
        ]);
    }

    public function withdrawActive(int $applicationId): ?Offer
    {
        $active = $this->active($applicationId, forUpdate: true);

        if ($active === null) {
            return null;
        }

        $withdrawn = $active->withdraw();
        $this->update($withdrawn);

        return $withdrawn;
    }

    public function dueForExpiry(DateTimeImmutable $now): array
    {
        return $this->connection->table('recruitment_offers')
            ->where('status', OfferStatus::Pending->value)
            ->where('expires_at', '<=', $this->format($now))
            ->orderBy('expires_at')
            ->get()
            ->map(fn (stdClass $row): Offer => $this->toOffer($row))
            ->all();
    }

    public function dueForReminder(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->connection->table('recruitment_offers')
            ->where('status', OfferStatus::Pending->value)
            ->whereNull('reminded_at')
            ->where('expires_at', '>', $this->format($from))
            ->where('expires_at', '<=', $this->format($until))
            ->orderBy('expires_at')
            ->get()
            ->map(fn (stdClass $row): Offer => $this->toOffer($row))
            ->all();
    }

    private function toOffer(stdClass $row): Offer
    {
        return new Offer(
            (int) $row->application_id,
            (string) $row->position,
            (int) $row->salary,
            new DateTimeImmutable((string) $row->start_date, new DateTimeZone('UTC')),
            $row->note === null ? null : (string) $row->note,
            $this->time((string) $row->expires_at),
            (int) $row->created_by,
            OfferStatus::from((string) $row->status),
            $row->answer_note === null ? null : (string) $row->answer_note,
            $row->answered_at === null ? null : $this->time((string) $row->answered_at),
            $this->time((string) $row->created_at),
            $row->reminded_at === null ? null : $this->time((string) $row->reminded_at),
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
