<?php

namespace Src\JobSeeker\Application\ImportSummary;

use DateTimeInterface;
use Src\JobSeeker\Domain\ImportSummaryRepository;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/** The import summary in the personal data export, while it exists. */
final readonly class ImportSummaryDataExporter implements PersonalDataExporter
{
    public function __construct(private ImportSummaryRepository $summaries) {}

    public function key(): string
    {
        return 'import_summary';
    }

    public function export(int $userId): PersonalDataSection
    {
        $summary = $this->summaries->find($userId);

        return new PersonalDataSection($summary === null ? [] : [[
            'created_at' => $summary->createdAt->format(DateTimeInterface::ATOM),
            'dismissed_at' => $summary->dismissedAt?->format(DateTimeInterface::ATOM),
            'items' => $summary->items,
        ]]);
    }
}
