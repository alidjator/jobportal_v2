<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Domain\ReportMessage;

/** A message with the name of its author and whether the reader wrote it. */
final readonly class ReportMessageView
{
    public function __construct(public ReportMessage $message, public string $authorName, public bool $mine) {}
}
