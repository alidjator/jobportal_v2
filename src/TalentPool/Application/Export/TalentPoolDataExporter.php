<?php

namespace Src\TalentPool\Application\Export;

use DateTimeInterface;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/** The TalentPool part of the personal data export: the companies that keep the job seeker in their talent pool, and since when. The company's category and its notes are the company's own. */
final readonly class TalentPoolDataExporter implements PersonalDataExporter
{
    public function __construct(private EntryRepository $entries, private CompanyDirectory $companies) {}

    public function key(): string
    {
        return 'talent_pool';
    }

    public function export(int $userId): PersonalDataSection
    {
        $entries = $this->entries->ofCandidate($userId);
        $companies = $this->companies->summaries(array_map(static fn (Entry $entry): int => $entry->companyId, $entries));

        return new PersonalDataSection(array_map(static fn (Entry $entry): array => [
            'company' => ($companies[$entry->companyId] ?? null)?->name,
            'saved_at' => $entry->createdAt->format(DateTimeInterface::ATOM),
        ], $entries));
    }
}
