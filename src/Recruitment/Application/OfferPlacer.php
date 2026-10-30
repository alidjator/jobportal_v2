<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\OfferTerms;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Puts an offer on an application: withdraws the offer that was pending (a new offer replaces it) and saves the new one. It runs inside the caller's
 * transaction, with the application row already locked. The new offer gets its letter (see {@see OfferLetters}).
 */
final readonly class OfferPlacer
{
    public function __construct(
        private OfferRepository $offers,
        private JobDirectory $jobs,
        private CandidateMessageRules $messageRules,
        private ReferenceValues $references,
        private Settings $settings,
        private OfferLetters $letters,
        private CheckTypeRepository $checkTypes,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-offer`, `probation-not-allowed`, `probation-too-long`, `contract-too-long` or `message-link-not-allowed`
     * @throws AccessDenied `feature-disabled` for an offer that depends on checks while pre-employment checks are switched off
     */
    public function place(RecruitmentMember $member, Application $application, OfferInput $input): OfferChanged
    {
        $now = $this->clock->now();
        $applicationId = (int) $application->id;
        $this->messageRules->assertAllowed($application->companyId, $input->note, 'offer.note');

        $offer = Offer::make(
            $applicationId,
            $input->position ?? $this->jobs->titles([$application->jobId])[$application->jobId] ?? '',
            $input->salary,
            $input->startDate,
            $input->note,
            $input->expiresAt,
            $member->userId,
            $now,
            $this->terms($application->companyId, $input->terms),
            $this->conditionTypeIds($application->companyId, $input->conditionCheckTypeIds),
        );

        $previous = $this->offers->active($applicationId, forUpdate: true);

        if ($previous instanceof Offer) {
            $this->offers->update($previous->withdraw());
        }

        $saved = $this->letters->issue($this->offers->add($offer), $application, $member, $input->letterUploadId);

        return OfferChanged::of($previous instanceof Offer ? OfferChange::Revised : OfferChange::Sent, $application, $saved, $now);
    }

    /**
     * The check types an offer depends on: all of them types of the company that are in use. Naming none leaves the offer unconditional.
     *
     * @param  list<int>  $ids
     * @return list<int>
     *
     * @throws AccessDenied `feature-disabled`
     * @throws BusinessRuleViolation `invalid-offer`
     */
    private function conditionTypeIds(int $companyId, array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        if (! $this->settings->boolean(RecruitmentSettings::PRE_EMPLOYMENT_CHECKS_ENABLED)) {
            throw new AccessDenied('Pre-employment checks are not available yet.', 'feature-disabled');
        }

        foreach ($ids as $id) {
            if (! ($this->checkTypes->find($companyId, $id)->isActive ?? false)) {
                $message = 'The check type :type does not exist or is not in use.';

                throw new BusinessRuleViolation($message, 'invalid-offer', extensions: ['errors' => ['condition_check_type_ids' => [$message]]], parameters: ['type' => $id]);
            }
        }

        return $ids;
    }

    /**
     * The terms with the benefits turned into a snapshot of their names in both languages; a benefit that does not exist or is not active is refused.
     *
     * @throws BusinessRuleViolation
     */
    private function terms(int $companyId, OfferTermsInput $input): OfferTerms
    {
        $ids = array_values(array_unique($input->benefitIds));

        foreach ($ids as $id) {
            if (! $this->references->isActive('benefits', $id)) {
                $message = 'The benefit :benefit does not exist or is not active.';

                throw new BusinessRuleViolation($message, 'invalid-offer', extensions: ['errors' => ['benefit_ids' => [$message]]], parameters: ['benefit' => $id]);
            }
        }

        $indonesian = $this->references->names('benefits', $ids, 'id');
        $english = $this->references->names('benefits', $ids, 'en');

        return OfferTerms::make(
            $input->employmentType,
            $input->contractMonths,
            $input->probationMonths,
            $input->workMode,
            $input->workLocation,
            $input->salaryBasis,
            $input->allowances,
            array_map(static fn (int $id): array => ['id' => $id, 'name' => ['id' => $indonesian[$id] ?? '', 'en' => $english[$id] ?? '']], $ids),
            $this->settings->boolean(RecruitmentSettings::OFFER_LABOR_RULES, $companyId),
        );
    }
}
