<?php

namespace Src\Messaging\Application\Reports;

use Src\Messaging\Domain\MessageReport;
use Src\Messaging\Domain\MessageReportRepository;
use Src\Messaging\Domain\ReportStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** The conversation reports for admins holding `messages.moderate`: open ones first, oldest first; then the decided ones, newest first. */
final readonly class ListMessageReports
{
    public function __construct(private MessageReportRepository $reports) {}

    /** @return Page<MessageReport> */
    public function execute(?ReportStatus $status, PageRequest $request): Page
    {
        return $this->reports->page($status, $request);
    }
}
