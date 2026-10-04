<?php

namespace Src\Notification\Application\InApp;

use DateTimeImmutable;

/**
 * One in-app notification as the owner reads it. The server stores no text: `type` is a stable key such as
 * `application.moved_to_interview` and `data` holds the structured parameters, so clients write and translate the message.
 */
final readonly class InAppNotification
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
        public ?DateTimeImmutable $readAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
