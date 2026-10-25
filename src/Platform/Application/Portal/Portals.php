<?php

namespace Src\Platform\Application\Portal;

use DateTimeImmutable;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;

/** The stored portal: the live identity, the live brand of each group and company, and the drafts. Provided by the database. */
interface Portals
{
    /** The live identity; null when none was ever published (the marketplace). */
    public function identity(): ?PortalIdentity;

    public function saveIdentity(PortalIdentity $identity, ?int $adminId, DateTimeImmutable $at): void;

    /** The hash the last warning email named, or null. */
    public function alertSignature(): ?string;

    public function rememberAlertSignature(?string $signature): void;

    /** The live brand of a group or company; null when it has none (the default brand). */
    public function brand(PortalOwner $owner): ?PortalBrandValues;

    public function saveBrand(PortalOwner $owner, PortalBrandValues $brand, ?int $userId, DateTimeImmutable $at): void;

    public function draft(PortalOwner $owner): ?PortalDraft;

    /** @param  array<string, mixed>  $payload */
    public function saveDraft(PortalOwner $owner, array $payload, ?int $updatedBy, DateTimeImmutable $at): void;

    public function deleteDraft(PortalOwner $owner): void;
}
