<?php

namespace Src\Job\Application;

use Src\Job\Application\Browse\PublicJobView;
use Src\Job\Application\Browse\PublicJobViewBuilder;
use Src\Job\Domain\JobContent;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Company\PublicCompanyCard;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A member sees a job they may see as the public will: the public view of the saved content in every status, with what still blocks publishing (`docs/design/phase-8-job-posting.md`
 * section 1). Nothing is counted or logged. A company the public cannot see (hidden by its own admin) still previews its jobs, under its name without a logo.
 */
final readonly class PreviewJob
{
    public function __construct(
        private JobAccess $access,
        private PublicJobViewBuilder $views,
        private PublicCompanies $companies,
        private CompanyDirectory $directory,
        private JobCompleteness $completeness,
    ) {}

    /**
     * @return array{view: PublicJobView, ready: bool, missing: list<string>}
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, int $jobId, string $language): array
    {
        $member = $this->access->member($actor);
        $job = $this->access->job($member, $jobId);

        return [
            'view' => $this->views->build($job, $this->card($job->companyId), $language),
            ...$this->publishCheck($job->content, $job->companyId),
        ];
    }

    /** @return array{ready: bool, missing: list<string>} the fields `PublishJob` would refuse, by their API names */
    private function publishCheck(JobContent $content, int $companyId): array
    {
        try {
            $this->completeness->assertComplete($content, $companyId, checkDeadlineRange: true);
        } catch (BusinessRuleViolation $problem) {
            /** @var array<string, mixed> $errors */
            $errors = $problem->extensions()['errors'] ?? [];

            return ['ready' => false, 'missing' => array_map('strval', array_keys($errors))];
        }

        return ['ready' => true, 'missing' => []];
    }

    private function card(int $companyId): PublicCompanyCard
    {
        $card = $this->companies->card($companyId);

        if ($card !== null) {
            return $card;
        }

        $summary = $this->directory->summaries([$companyId])[$companyId] ?? throw EntityNotFound::of('Company', $companyId);

        return new PublicCompanyCard($companyId, $summary->name, $summary->slug, $this->directory->isVerified($companyId), null);
    }
}
