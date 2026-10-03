<?php

namespace Src\Content\Application\Commands;

use Src\Content\Domain\ContactNotifier;
use Src\Content\Domain\ContactRepository;

final class SendContactMessage
{
    public function __construct(private ContactRepository $repository, private ContactNotifier $notifier) {}

    /** @param  array{name: string, email: string, subject: string, message: string}  $contact */
    public function __invoke(array $contact): void
    {
        $this->repository->store($contact);
        $this->notifier->notifyAdmins($contact);
    }
}
