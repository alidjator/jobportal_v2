<?php

namespace Src\Company\Application\Messages;

use Src\Company\Domain\MessageDefaults;
use Src\Company\Domain\MessageDefaultsRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The default texts of the company. Any member reads them: the dialogs of the recruiters start with them. */
final readonly class GetMessageDefaults
{
    public function __construct(private MessageDefaultsRepository $defaults) {}

    /** @throws AccessDenied `not-a-company-member` */
    public function execute(Actor $actor): MessageDefaults
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can read the message defaults.', 'not-a-company-member');
        }

        return $this->defaults->find($actor->membership->companyId);
    }
}
