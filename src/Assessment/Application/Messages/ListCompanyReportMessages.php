<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Domain\ReportMessageAuthor;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The questions asked about the signed report of an invitation and the answers, for the members who may read its result. */
final readonly class ListCompanyReportMessages
{
    public function __construct(private CompanyReportAccess $access, private ReportThreads $threads) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $invitationId): ReportThread
    {
        [, $report] = $this->access->of($actor, $invitationId);

        return $this->threads->build($report, ReportMessageAuthor::Company, (int) $actor->id);
    }
}
