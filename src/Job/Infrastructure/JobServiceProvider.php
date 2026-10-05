<?php

namespace Src\Job\Infrastructure;

use Src\Job\Application\JobSettings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobServiceProvider extends ContextServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...JobSettings::definitions());
        });
    }
}
