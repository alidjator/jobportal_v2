<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The picture of a candidate a company may find. A candidate without one, or whose file has gone missing, is the same 404. Viewing a picture is not logged. */
final readonly class GetCandidateAvatar
{
    private const array MIME_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function __construct(private CandidateAccess $access, private CandidateReader $candidates, private FileStorage $files) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, string $slug): CandidateAvatar
    {
        $this->access->viewer($actor);
        $path = ($this->candidates->findBySlug($slug) ?? throw EntityNotFound::of('Candidate', $slug))->avatarPath;
        $contents = $path === null ? null : $this->files->get($path);

        if ($path === null || $contents === null) {
            throw new EntityNotFound('There is no profile picture.', 'avatar-not-found');
        }

        return new CandidateAvatar($contents, self::MIME_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream');
    }
}
