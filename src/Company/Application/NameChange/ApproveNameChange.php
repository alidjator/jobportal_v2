<?php

namespace Src\Company\Application\NameChange;

use DateTimeImmutable;
use RuntimeException;
use Src\Company\Domain\Events\CompanyNameChangeDecided;
use Src\Company\Domain\NameChangeRequest;
use Src\Shared\Application\Actor;

/** An admin approves a name change: the company takes the new name and keeps its verification badge. The slug stays as it is. */
final class ApproveNameChange extends AdminNameChangeDecision
{
    public function execute(Actor $actor, int $requestId, ?string $ipAddress, ?string $userAgent): void
    {
        $this->run($actor, $requestId, null, $ipAddress, $userAgent);
    }

    protected function decision(): string
    {
        return CompanyNameChangeDecided::APPROVED;
    }

    protected function apply(NameChangeRequest $request, int $adminId, ?string $note, DateTimeImmutable $at): NameChangeRequest
    {
        $approved = $request->approve($adminId, $at);

        $company = $this->companies->findById($request->companyId) ?? throw new RuntimeException("Company {$request->companyId} of a name change request does not exist.");
        $company->changeProfile($company->profile()->with(['name' => $request->newName]));
        $this->companies->save($company);

        return $approved;
    }
}
