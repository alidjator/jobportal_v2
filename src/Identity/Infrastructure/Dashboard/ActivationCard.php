<?php

namespace Src\Identity\Infrastructure\Dashboard;

use Src\Identity\Application\Activation\GetActivationProgress;
use Src\Identity\Application\IdentityPermissions;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The share of the accounts imported from karir.pkp that signed in, for job seekers and for company owners (`docs/design/phase-8-activation.md` section 10). Needs `users.view`. */
final readonly class ActivationCard implements DashboardCardProvider
{
    public function __construct(private GetActivationProgress $progress) {}

    public function key(): string
    {
        return 'activation';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): string
    {
        return IdentityPermissions::USERS_VIEW;
    }

    public function build(DashboardScope $scope): array
    {
        $rates = $this->progress->signInRates();

        return ['job_seekers_signed_in_percent' => $rates['job_seekers'], 'companies_signed_in_percent' => $rates['companies']];
    }
}
