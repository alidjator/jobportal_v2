<?php

namespace Src\Recruitment\Infrastructure;

use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\ProvisionDefaultStages;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\ScreeningQuestionReader;
use Src\Recruitment\Application\ScreeningQuestionUsage;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseJobQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseStageRepository;
use Src\Recruitment\Interface\Console\ProvisionDefaultStagesCommand;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class RecruitmentServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        StageRepository::class => DatabaseStageRepository::class,
        DefaultStageProvisioner::class => ProvisionDefaultStages::class,
        ScreeningQuestionRepository::class => DatabaseScreeningQuestionRepository::class,
        ScreeningQuestionReader::class => DatabaseScreeningQuestionReader::class,
        ScreeningQuestionUsage::class => NoApplicationsYet::class,
        JobApplicationCheck::class => NoApplicationsYet::class,
        JobQuestionRepository::class => DatabaseJobQuestionRepository::class,
    ];

    public function register(): void
    {
        $this->commands([ProvisionDefaultStagesCommand::class]);

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...RecruitmentSettings::definitions());
        });
    }
}
