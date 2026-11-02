<?php

namespace Src\Assessment\Domain;

/** The PDF of a signed report as it was rendered once and kept on the private disk: where it is and its SHA-256, so the file a company downloads never changes. */
final readonly class StoredReportPdf
{
    public function __construct(public string $path, public string $sha256) {}
}
