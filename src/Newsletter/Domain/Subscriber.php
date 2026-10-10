<?php

namespace Src\Newsletter\Domain;

use DateTimeImmutable;

/**
 * A person on the email list. `pending` until they open the link in the confirmation email (valid 3 days), then `confirmed`; `unsubscribed` is kept so nobody who
 * left is written to again. The address is stored in lower case.
 */
final readonly class Subscriber
{
    public const int CONFIRMATION_DAYS = 3;

    /** A pending subscriber gets a new confirmation email at most this often. */
    public const int RESEND_AFTER_SECONDS = 600;

    public function __construct(
        public string $email,
        public SubscriberStatus $status,
        public string $locale,
        public ?string $confirmTokenHash,
        public ?DateTimeImmutable $confirmExpiresAt,
        public ?DateTimeImmutable $confirmationSentAt,
        public ?DateTimeImmutable $confirmedAt,
        public ?DateTimeImmutable $unsubscribedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    public static function pending(string $email, string $locale, ConfirmationToken $token, DateTimeImmutable $now): self
    {
        return new self($email, SubscriberStatus::Pending, $locale, $token->hash(), $now->modify('+'.self::CONFIRMATION_DAYS.' days'), $now, null, null, $now, $now);
    }

    /** A new confirmation email with a fresh token: for a pending subscriber whose last email is old, and for one who left and subscribes again. */
    public function reconfirming(string $locale, ConfirmationToken $token, DateTimeImmutable $now): self
    {
        return new self(
            $this->email, SubscriberStatus::Pending, $locale, $token->hash(), $now->modify('+'.self::CONFIRMATION_DAYS.' days'), $now,
            null, null, $this->createdAt, $now, $this->id,
        );
    }

    public function confirmed(DateTimeImmutable $now): self
    {
        return new self($this->email, SubscriberStatus::Confirmed, $this->locale, null, null, $this->confirmationSentAt, $now, null, $this->createdAt, $now, $this->id);
    }

    public function unsubscribed(DateTimeImmutable $now): self
    {
        return new self($this->email, SubscriberStatus::Unsubscribed, $this->locale, null, null, $this->confirmationSentAt, $this->confirmedAt, $now, $this->createdAt, $now, $this->id);
    }

    public function canBeConfirmedAt(DateTimeImmutable $now): bool
    {
        return $this->status === SubscriberStatus::Pending && $this->confirmExpiresAt !== null && $this->confirmExpiresAt > $now;
    }

    public function mayGetAnotherConfirmationAt(DateTimeImmutable $now): bool
    {
        return $this->confirmationSentAt === null || $this->confirmationSentAt->modify('+'.self::RESEND_AFTER_SECONDS.' seconds') <= $now;
    }
}
