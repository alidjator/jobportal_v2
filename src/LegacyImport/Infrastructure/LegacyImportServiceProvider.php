<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Filesystem\FilesystemManager;
use Src\LegacyImport\Application\AccountsImporter;
use Src\LegacyImport\Application\AccountWriter;
use Src\LegacyImport\Application\ApplicationsImporter;
use Src\LegacyImport\Application\ApplicationWriter;
use Src\LegacyImport\Application\CampaignsImporter;
use Src\LegacyImport\Application\CampaignWriter;
use Src\LegacyImport\Application\CompaniesImporter;
use Src\LegacyImport\Application\CompanySummaryWriter;
use Src\LegacyImport\Application\CompanyWriter;
use Src\LegacyImport\Application\EducationsImporter;
use Src\LegacyImport\Application\ExperiencesImporter;
use Src\LegacyImport\Application\ImagesImporter;
use Src\LegacyImport\Application\Importer;
use Src\LegacyImport\Application\ImportRunner;
use Src\LegacyImport\Application\ImportSummaryFlusher;
use Src\LegacyImport\Application\JobQuestionsImporter;
use Src\LegacyImport\Application\JobSeekerSummaryWriter;
use Src\LegacyImport\Application\JobSeekerWriter;
use Src\LegacyImport\Application\JobsImporter;
use Src\LegacyImport\Application\JobWriter;
use Src\LegacyImport\Application\LanguagesImporter;
use Src\LegacyImport\Application\LegacyFiles;
use Src\LegacyImport\Application\LegacySource;
use Src\LegacyImport\Application\LinksImporter;
use Src\LegacyImport\Application\LocationLookup;
use Src\LegacyImport\Application\MastersImporter;
use Src\LegacyImport\Application\MasterWriter;
use Src\LegacyImport\Application\NewMasterReader;
use Src\LegacyImport\Application\NewsletterSubscribersImporter;
use Src\LegacyImport\Application\NewsletterSubscriberWriter;
use Src\LegacyImport\Application\ProfilesImporter;
use Src\LegacyImport\Application\QuestionsImporter;
use Src\LegacyImport\Application\ReportWriter;
use Src\LegacyImport\Application\ResumesImporter;
use Src\LegacyImport\Application\ScreeningWriter;
use Src\LegacyImport\Application\SkillsImporter;
use Src\LegacyImport\Domain\IdMap;
use Src\LegacyImport\Interface\Console\LegacyImportCommand;
use Src\LegacyImport\Interface\Console\LegacyMastersCheckCommand;
use Src\Shared\Application\Clock;
use Src\Shared\Infrastructure\ContextServiceProvider;

/**
 * The data migration from karir.pkp: an operational tool (artisan commands, no API). Each part of the import adds its importer to the `IMPORTERS` list.
 */
final class LegacyImportServiceProvider extends ContextServiceProvider
{
    /** @var list<class-string<Importer>> */
    public const array IMPORTERS = [
        MastersImporter::class,
        AccountsImporter::class,
        CompaniesImporter::class,
        ImagesImporter::class,
        ProfilesImporter::class,
        ExperiencesImporter::class,
        EducationsImporter::class,
        SkillsImporter::class,
        LanguagesImporter::class,
        LinksImporter::class,
        ResumesImporter::class,
        JobsImporter::class,
        QuestionsImporter::class,
        JobQuestionsImporter::class,
        ApplicationsImporter::class,
        CampaignsImporter::class,
        NewsletterSubscribersImporter::class,
    ];

    /** @var array<class-string, class-string> */
    public array $bindings = [
        IdMap::class => DatabaseIdMap::class,
        LegacySource::class => DatabaseLegacySource::class,
        NewMasterReader::class => DatabaseNewMasterReader::class,
        MasterWriter::class => DatabaseMasterWriter::class,
        AccountWriter::class => DatabaseAccountWriter::class,
        CompanyWriter::class => DatabaseCompanyWriter::class,
        JobSeekerWriter::class => DatabaseJobSeekerWriter::class,
        JobWriter::class => DatabaseJobWriter::class,
        ScreeningWriter::class => DatabaseScreeningWriter::class,
        ApplicationWriter::class => DatabaseApplicationWriter::class,
        CampaignWriter::class => DatabaseCampaignWriter::class,
        JobSeekerSummaryWriter::class => DatabaseJobSeekerSummaryWriter::class,
        CompanySummaryWriter::class => DatabaseCompanySummaryWriter::class,
        NewsletterSubscriberWriter::class => DatabaseNewsletterSubscriberWriter::class,
        LocationLookup::class => DatabaseLocationLookup::class,
        LegacyFiles::class => LocalLegacyFiles::class,
    ];

    public function register(): void
    {
        $this->app->singleton(DatabaseLegacySource::class);
        $this->app->bind(LegacySource::class, DatabaseLegacySource::class);

        $this->app->bind(ReportWriter::class, fn ($app): ReportWriter => new JsonFileReportWriter($app->make(FilesystemManager::class)->disk('local')));

        $this->app->bind(ImportRunner::class, fn ($app): ImportRunner => new ImportRunner(
            array_map(fn (string $importer): Importer => $app->make($importer), self::IMPORTERS),
            $app->make(IdMap::class),
            $app->make(Clock::class),
            $app->make(ReportWriter::class),
            $app->make(ImportSummaryFlusher::class),
        ));

        $this->app->bind(LocalLegacyFiles::class, fn (): LocalLegacyFiles => new LocalLegacyFiles((string) config('legacy_import.files_root'), (int) config('legacy_import.max_file_bytes')));

        $this->commands([LegacyImportCommand::class, LegacyMastersCheckCommand::class]);
    }
}
