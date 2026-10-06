<?php

namespace Src\Identity\Application\Account;

use Psr\Log\LoggerInterface;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Throwable;

/** Removes the picture of the caller's account. An account without a picture is left as it is (nothing to do, not an error). */
final readonly class RemoveMyAvatar
{
    public function __construct(
        private AvatarOwner $owner,
        private UserRepository $users,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /** @throws AccessDenied `public-account-required` */
    public function execute(Actor $actor): void
    {
        $user = $this->owner->user($actor);
        $previous = $user->avatarPath();

        if ($previous === null) {
            return;
        }

        $user->changeAvatar(null);
        $this->users->save($user);

        try {
            $this->files->delete($previous);
        } catch (Throwable $e) {
            $this->logger->warning('An avatar file could not be deleted.', ['path' => $previous, 'exception' => $e]);
        }
    }
}
