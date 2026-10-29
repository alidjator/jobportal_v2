<?php

namespace Src\Assessment\Application\Provider;

use Src\Assessment\Application\Institution\PrivateImages;
use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Throwable;

/**
 * A psychologist puts, replaces or takes off their own signature image. A report copies the file when it is signed, so a later change never alters a report that was issued. The new file is
 * stored first and the psychologist changed next; the old file is deleted last.
 */
final readonly class ChangeMySignature
{
    public const string DIRECTORY = 'assessment/signatures';

    public const int MAX_KILOBYTES = 1024;

    private const int MAX_WIDTH = 600;

    private const int MAX_HEIGHT = 300;

    public function __construct(
        private ReviewAccess $access,
        private PsychologistRepository $psychologists,
        private PrivateImages $images,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws BusinessRuleViolation `file-too-large` or `invalid-image`
     */
    public function put(Actor $actor, string $contents): Psychologist
    {
        $psychologist = $this->access->psychologist($actor);
        $path = $this->images->store(self::DIRECTORY.'/'.$psychologist->id, $contents, self::MAX_KILOBYTES, self::MAX_WIDTH, self::MAX_HEIGHT);

        try {
            [$saved, $previous] = $this->swap((int) $psychologist->id, $path);
        } catch (Throwable $e) {
            $this->images->deleteQuietly($path);

            throw $e;
        }

        $this->images->deleteQuietly($previous);

        return $saved;
    }

    /** @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended` */
    public function remove(Actor $actor): Psychologist
    {
        [$saved, $previous] = $this->swap((int) $this->access->psychologist($actor)->id, null);
        $this->images->deleteQuietly($previous);

        return $saved;
    }

    /** @return array{0: Psychologist, 1: string|null} the changed psychologist and the path of the signature it replaced */
    private function swap(int $psychologistId, ?string $path): array
    {
        return $this->transactions->run(function () use ($psychologistId, $path): array {
            $psychologist = $this->psychologists->findById($psychologistId, forUpdate: true) ?? throw EntityNotFound::of('Psychologist', $psychologistId);
            $previous = $psychologist->signaturePath();
            $psychologist->changeSignature($path);

            return [$this->psychologists->save($psychologist), $previous];
        });
    }
}
