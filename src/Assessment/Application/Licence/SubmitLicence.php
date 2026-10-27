<?php

namespace Src\Assessment\Application\Licence;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\LicenceKind;
use Src\Assessment\Domain\LicenceSubmission;
use Src\Assessment\Domain\LicenceSubmissionRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A psychologist submits a renewed STR or SILP with its document. The current licence stays in force until its own date; the new one takes its place when the
 * platform admin approves. The document is stored first and the submission saved next; a failure removes the file again.
 */
final readonly class SubmitLicence
{
    private const string DIRECTORY = 'assessment/licences';

    public function __construct(
        private ReviewAccess $access,
        private LicenceSubmissionRepository $submissions,
        private PsychologistRepository $psychologists,
        private FileStorage $files,
        private TransactionManager $transactions,
        private LoggerInterface $logger,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     * @throws BusinessRuleViolation `licence-already-expired`, `file-too-large` or `invalid-licence-document`
     * @throws StateConflict `licence-pending` when a submission of the same licence waits for a decision
     */
    public function execute(Actor $actor, LicenceKind $kind, string $number, DateTimeImmutable $validUntil, string $contents, string $originalName): LicenceSubmission
    {
        $psychologist = $this->access->psychologist($actor);

        if ($validUntil->format('Y-m-d') < $this->clock->now()->format('Y-m-d')) {
            throw new BusinessRuleViolation('The licence has expired already.', 'licence-already-expired');
        }

        LicenceDocument::assertValid($contents);

        $path = self::DIRECTORY.'/'.$psychologist->id.'/'.bin2hex(random_bytes(16)).'.pdf';
        $this->files->put($path, $contents);

        try {
            return $this->transactions->run(function () use ($psychologist, $kind, $number, $validUntil, $path, $originalName, $contents): LicenceSubmission {
                // The psychologist row serialises two submissions of the same licence; the partial unique index is the safety net.
                $this->psychologists->findById((int) $psychologist->id, forUpdate: true);

                if ($this->submissions->hasPending((int) $psychologist->id, $kind)) {
                    throw new StateConflict('A submission of this licence is waiting for a decision.', 'licence-pending');
                }

                return $this->submissions->save(LicenceSubmission::submit(
                    (int) $psychologist->id, $kind, trim($number), $validUntil, $path, LicenceDocument::cleanName($originalName), strlen($contents),
                ));
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A licence document could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
