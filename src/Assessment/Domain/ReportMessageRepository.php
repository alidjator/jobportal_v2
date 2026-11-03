<?php

namespace Src\Assessment\Domain;

interface ReportMessageRepository
{
    public function add(ReportMessage $message): ReportMessage;

    /**
     * The messages of the report, oldest first.
     *
     * @return list<ReportMessage>
     */
    public function ofReport(int $reportId): array;

    /**
     * The accounts of the company members who asked something about the report.
     *
     * @return list<int>
     */
    public function askerUserIds(int $reportId): array;
}
