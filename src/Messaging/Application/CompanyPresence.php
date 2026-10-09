<?php

namespace Src\Messaging\Application;

/** Whether any member of a company is online (connected to the company's presence channel). Nothing is stored; an unreachable server reads as offline. */
interface CompanyPresence
{
    public function isOnline(int $companyId): bool;
}
