<?php

namespace Src\Newsletter\Domain;

/** The sending of one issue to one subscriber. */
final readonly class Delivery
{
    public function __construct(
        public int $id,
        public int $issueId,
        public int $subscriberId,
        public DeliveryStatus $status,
    ) {}
}
