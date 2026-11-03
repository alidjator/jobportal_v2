<?php

namespace Src\Workforce\Application\Requisition;

/** A document with its bytes, ready to be sent to the reader. */
final readonly class RequisitionDocumentContent
{
    public function __construct(public string $name, public string $contentType, public string $contents) {}
}
