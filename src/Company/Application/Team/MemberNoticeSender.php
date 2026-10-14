<?php

namespace Src\Company\Application\Team;

/** Emails a company member about a team action that concerns them. Each notice is queued in the member's own language. */
interface MemberNoticeSender
{
    public function suspended(string $email, string $language, string $companyName, ?string $message): void;

    public function reactivated(string $email, string $language, string $companyName): void;

    /** @param  ?string  $previousOwner  the name of the former owner, or null when a platform admin moved the ownership */
    public function ownershipGained(string $email, string $language, string $companyName, ?string $previousOwner): void;

    public function ownershipHandedOver(string $email, string $language, string $companyName, string $newOwner): void;
}
