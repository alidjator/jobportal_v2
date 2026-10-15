<?php

namespace Src\Company\Application\NameChange;

use DateTimeImmutable;
use Src\Company\Domain\Events\CompanyNameChangeDecided;
use Src\Company\Domain\NameChangeRequest;
use Src\Shared\Application\Actor;

/** An admin rejects a name change with a note the company admins read. The company may ask again. */
final class RejectNameChange extends AdminNameChangeDecision
{
    public function execute(Actor $actor, int $requestId, string $note, ?string $ipAddress, ?string $userAgent): void
    {
        $this->run($actor, $requestId, $note, $ipAddress, $userAgent);
    }

    protected function decision(): string
    {
        return CompanyNameChangeDecided::REJECTED;
    }

    protected function apply(NameChangeRequest $request, int $adminId, ?string $note, DateTimeImmutable $at): NameChangeRequest
    {
        return $request->reject($adminId, (string) $note, $at);
    }
}
