<?php

namespace Src\Messaging\Application;

/** A message that passed its checks, with its files already stored. It is delivered to one conversation, or to many (a bulk message shares the files). */
final readonly class PreparedMessage
{
    /** @param  list<int>  $attachmentIds */
    public function __construct(public ?string $body, public array $attachmentIds) {}
}
