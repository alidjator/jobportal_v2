<?php

namespace Src\Assessment\Application\Institution;

use Psr\Log\LoggerInterface;
use Src\Assessment\Application\Delivery\AttachmentFile;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Throwable;

/**
 * The pictures of the institutions and of their psychologists (a logo, a signature): checked against a size, decoded and encoded again so nothing but the picture remains, and kept on the
 * private disk under a random name. Never reachable by a URL; the application hands them out through endpoints that check access.
 */
final readonly class PrivateImages
{
    private const array MIME_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public function __construct(
        private ImageProcessor $images,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * Checks, cleans and stores the picture, and returns its path.
     *
     * @throws BusinessRuleViolation `file-too-large` or `invalid-image`
     */
    public function store(string $directory, string $contents, int $maxKilobytes, int $maxWidth, int $maxHeight): string
    {
        if (strlen($contents) > $maxKilobytes * 1024) {
            throw new BusinessRuleViolation('The file is larger than the allowed :max KB.', 'file-too-large', extensions: ['max_size_kb' => $maxKilobytes], parameters: ['max' => $maxKilobytes]);
        }

        $image = $this->images->process($contents, $maxWidth, $maxHeight);
        $path = $directory.'/'.bin2hex(random_bytes(16)).'.'.$image->extension;

        $this->files->put($path, $image->contents);

        return $path;
    }

    /** The picture of a path as a response body, or a 404 when the file is gone. */
    public function read(string $path, string $name): AttachmentFile
    {
        $contents = $this->files->get($path) ?? throw new EntityNotFound('Image not found.', 'image-not-found');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return new AttachmentFile($contents, self::MIME_TYPES[$extension] ?? 'application/octet-stream', $name.'.'.$extension);
    }

    /** A failure to delete only leaves a stray file behind, so it is logged and not thrown. */
    public function deleteQuietly(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A picture could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
