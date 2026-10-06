<?php

namespace Src\Recruitment\Infrastructure;

use Src\Recruitment\Application\ProvisionDefaultStages;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Domain\StageRepository;
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
    ];

    public function register(): void
    {
        $this->commands([ProvisionDefaultStagesCommand::class]);

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...RecruitmentSettings::definitions());
        });
    }
}
