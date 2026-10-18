<?php

namespace Src\Recruitment\Application;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\LetterSource;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferLetter;
use Src\Recruitment\Domain\OfferLetterUpload;
use Src\Recruitment\Domain\OfferLetterUploadRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyLetterheads;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Throwable;

/**
 * Gives a new offer its letter: the company's own PDF when the request names an upload (it becomes the letter and the upload is used up), otherwise a PDF the
 * platform generates from the terms in the candidate's language. The file is kept under `recruitment/offers/{offer}/` and the offer records its source and the
 * SHA-256 of the exact file. It runs inside the caller's transaction, after the offer was saved (the file path needs its id).
 */
final readonly class OfferLetters
{
    public const string DIRECTORY = 'recruitment/offers';

    public function __construct(
        private OfferRepository $offers,
        private OfferLetterUploadRepository $uploads,
        private OfferLetterRenderer $renderer,
        private CompanyLetterheads $letterheads,
        private CompanyMemberAccounts $members,
        private ApplicantContacts $contacts,
        private FileStorage $files,
        private Clock $clock,
        private LoggerInterface $logger,
        private string $platformName,
    ) {}

    /** @throws BusinessRuleViolation `invalid-offer` when the upload is not one this application may use */
    public function issue(Offer $saved, Application $application, RecruitmentMember $member, ?int $uploadId): Offer
    {
        $offerId = (int) $saved->id;
        $path = self::DIRECTORY.'/'.$offerId.'/'.bin2hex(random_bytes(16)).'.pdf';
        $upload = $uploadId === null ? null : $this->usable($uploadId, $application);

        if ($upload instanceof OfferLetterUpload) {
            $contents = $this->files->get($upload->path) ?? throw $this->refusal('The uploaded letter is no longer available. Upload it again.');
            $source = LetterSource::Uploaded;
        } else {
            $contents = $this->renderer->render($this->document($saved, $application, $member));
            $source = LetterSource::Generated;
        }

        $this->files->put($path, $contents);
        $letter = new OfferLetter($path, $source, hash('sha256', $contents));
        $issued = $saved->withLetter($letter);
        $this->offers->update($issued);

        if ($upload instanceof OfferLetterUpload) {
            $this->uploads->delete((int) $upload->id);
            $this->deleteQuietly($upload->path);
        }

        return $issued;
    }

    /** @throws BusinessRuleViolation `invalid-offer` */
    private function usable(int $uploadId, Application $application): OfferLetterUpload
    {
        $upload = $this->uploads->find($uploadId);

        if (! $upload instanceof OfferLetterUpload || $upload->applicationId !== (int) $application->id || ! $upload->isUsableAt($this->clock->now())) {
            throw $this->refusal('The letter upload does not exist or has expired. Upload it again.');
        }

        return $upload;
    }

    private function refusal(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-offer', extensions: ['errors' => ['letter_upload_id' => [$message]]]);
    }

    private function document(Offer $offer, Application $application, RecruitmentMember $member): OfferLetterDocument
    {
        $terms = $offer->terms ?? throw new BusinessRuleViolation('An offer needs its terms before a letter can be made.', 'invalid-offer');
        $contact = $this->contacts->contact($application->userId);
        $sender = $this->members->summaries([$member->userId])[$member->userId] ?? null;

        return OfferLetterDocument::of(
            $offer,
            $terms,
            $this->letterheads->for($application->companyId),
            $contact === null ? '' : $contact->name,
            $contact === null ? 'id' : $contact->locale,
            $sender === null ? '' : $sender->name,
            $this->platformName,
            $this->clock->now(),
        );
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('An offer letter upload could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
