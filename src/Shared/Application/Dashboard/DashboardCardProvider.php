<?php

namespace Src\Shared\Application\Dashboard;

/**
 * One card of a dashboard, built by the context that owns the data. A context registers its providers in `DashboardCards` from its service provider, so the Dashboard
 * context knows no other context. The card is plain data (scalars and lists), ready to be sent as JSON.
 */
interface DashboardCardProvider
{
    /** The name of the card in the response; unique per audience. */
    public function key(): string;

    /** @return list<DashboardAudience> the dashboards the card appears on */
    public function audiences(): array;

    /** The admin permission the card needs on top of the dashboard permission (an admin card that shows another module's data), or null. */
    public function permission(): ?string;

    /** @return array<string, mixed> */
    public function build(DashboardScope $scope): array;
}
