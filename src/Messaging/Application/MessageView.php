<?php

namespace Src\Messaging\Application;

/** A message with the sender's name; a sender whose account was erased reads as "Deleted user". */
final readonly class MessageView
{
    public function __construct(public MessageRow $message, public string $senderName) {}
}
