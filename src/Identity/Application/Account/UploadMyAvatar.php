<?php

namespace Src\Identity\Application\Account;

use Psr\Log\LoggerInterface;
use Src\Identity\Application\IdentitySettings;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Throwable;

/**
 * A job seeker or company member sets their picture. The file is checked against the size setting, decoded and encoded again (so nothing but the picture
 * remains), shrunk to fit 512 by 512 (never enlarged or cropped) and stored under a random name. The new file is stored first and the account changed next;
 * the old file is deleted last, and a failure to delete it only leaves a stray file behind.
 */
final readonly class UploadMyAvatar
{
    private const string DIRECTORY = 'identity/avatars';

    private const int MAX_SIDE = 512;

    public function __construct(
        private AvatarOwner $owner,
        private UserRepository $users,
        private Settings $settings,
        private ImageProcessor $images,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `public-account-required`
     * @throws BusinessRuleViolation `file-too-large` or `invalid-image`
     */
    public function execute(Actor $actor, string $contents): void
    {
        $user = $this->owner->user($actor);
        $maxKb = $this->settings->integer(IdentitySettings::AVATAR_MAX_SIZE_KB);

        if (strlen($contents) > $maxKb * 1024) {
            throw new BusinessRuleViolation("The file is larger than the allowed {$maxKb} KB.", 'file-too-large', extensions: ['max_size_kb' => $maxKb]);
        }

        $image = $this->images->process($contents, self::MAX_SIDE, self::MAX_SIDE);
        $path = self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.'.$image->extension;
        $previous = $user->avatarPath();

        $this->files->put($path, $image->contents);

        try {
            $user->changeAvatar($path);
            $this->users->save($user);
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        if ($previous !== null) {
            $this->deleteQuietly($previous);
        }
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('An avatar file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
