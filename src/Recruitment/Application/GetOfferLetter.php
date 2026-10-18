<?php

namespace Src\Recruitment\Application;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The letter PDF of one offer for the members who may see its application. An offer sent before letters existed, or one whose file is missing, answers as not
 * found (a missing file is also reported). Every download is audited (`offer.letter_downloaded`) for the company activity log.
 */
final readonly class GetOfferLetter
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private OfferRepository $offers,
        private FileStorage $files,
        private AuditTrail $audit,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId, int $offerId, ?string $ipAddress = null, ?string $userAgent = null): ApplicationFile
    {
        $application = $this->lookup->find($actor, $applicationId)->application;
        $offer = $this->offers->findById($offerId);

        if (! $offer instanceof Offer || $offer->applicationId !== $applicationId || $offer->letter === null) {
            throw EntityNotFound::of('Offer letter', $offerId);
        }

        $contents = $this->files->get($offer->letter->path);

        if ($contents === null) {
            $this->logger->error('The letter of an offer is missing.', ['offer_id' => $offerId, 'path' => $offer->letter->path]);

            throw EntityNotFound::of('Offer letter', $offerId);
        }

        $this->audit->record(new AuditEntry(
            AuditActorType::User, $actor->id, AuditAction::OFFER_LETTER_DOWNLOADED, 'offer', $offerId, ['application_id' => $applicationId], $ipAddress, $userAgent, $this->clock->now(), $application->companyId,
        ));

        return new ApplicationFile($contents, 'offer-letter-'.$offerId);
    }
}
