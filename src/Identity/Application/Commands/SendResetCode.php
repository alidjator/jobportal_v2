<?php

namespace Src\Identity\Application\Commands;

use Src\Identity\Domain\AccountNotifier;
use Src\Identity\Domain\PasswordResetCodes;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class SendResetCode
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetCodes $codes,
        private readonly AccountNotifier $notifier,
    ) {}

    /** @return string kode reset (pemanggil menentukan apakah boleh diekspos) */
    public function __invoke(string $email): string
    {
        $userId = $this->users->findIdByEmail($email) ?? throw new EntityNotFound('Not Found');

        $code = $this->codes->issue($userId);
        $this->notifier->resetCodeIssued($userId, $code);

        return $code;
    }
}
