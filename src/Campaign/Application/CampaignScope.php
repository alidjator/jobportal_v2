<?php

namespace Src\Campaign\Application;

/** Whose campaigns an actor may handle: one company's (a company admin) or every campaign (a platform admin, `companyId` null). */
final readonly class CampaignScope
{
    public function __construct(public ?int $companyId, public bool $isAdmin) {}
}
