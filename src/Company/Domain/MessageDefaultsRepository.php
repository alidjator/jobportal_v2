<?php

namespace Src\Company\Domain;

interface MessageDefaultsRepository
{
    /** The defaults of the company; all empty when it never set any. */
    public function find(int $companyId): MessageDefaults;

    public function save(int $companyId, MessageDefaults $defaults, int $changedByUserId): void;

    /** The host of the company's website without `www.`, or null when it has none. */
    public function websiteDomain(int $companyId): ?string;
}
