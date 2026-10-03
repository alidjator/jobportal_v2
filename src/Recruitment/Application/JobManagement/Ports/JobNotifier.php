<?php

namespace Src\Recruitment\Application\JobManagement\Ports;

interface JobNotifier
{
    /** @param  array<string, mixed>  $job */
    public function jobCreated(int $ownerUserId, array $job): void;

    /** @param  array<string, mixed>  $job */
    public function jobEditWaitingApproval(int $ownerUserId, array $job): void;
}
