<?php

namespace Src\Messaging\Application;

/** What a sender gives for a message: a text, files, or both. */
final readonly class MessageInput
{
    /** @param  list<AttachmentUpload>  $uploads */
    public function __construct(public ?string $body, public array $uploads = []) {}
}
