<?php

namespace Src\Shared\Application\Privacy;

/** What one exporter found: the values for `data.json` and the stored files that go into the archive's `files/` folder. */
final readonly class PersonalDataSection
{
    /**
     * @param  array<array-key, mixed>  $data  values that can be written as JSON, an object or a list; dates as ISO 8601 text, never storage paths
     * @param  list<ExportedFile>  $files
     */
    public function __construct(public array $data, public array $files = []) {}
}
