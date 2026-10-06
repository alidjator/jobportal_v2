<?php

namespace Src\Identity\Application\Account;

use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The caller's own picture. An account without one, or a file that has gone missing, is the same 404. */
final readonly class GetMyAvatar
{
    private const array MIME_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function __construct(private AvatarOwner $owner, private FileStorage $files) {}

    /**
     * @throws AccessDenied `public-account-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor): AvatarImage
    {
        $path = $this->owner->user($actor)->avatarPath();
        $contents = $path === null ? null : $this->files->get($path);

        if ($path === null || $contents === null) {
            throw new EntityNotFound('There is no profile picture.', 'avatar-not-found');
        }

        return new AvatarImage($contents, self::MIME_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream');
    }
}
