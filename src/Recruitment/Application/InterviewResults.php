<?php

namespace Src\Recruitment\Application;

/** Reads the results of interviews from their sent scorecards. It checks nothing about who asks. */
interface InterviewResults
{
    /**
     * @param  list<int>  $interviewIds
     * @return array<int, InterviewResult> by interview id; an interview without a sent scorecard is missing
     */
    public function forInterviews(array $interviewIds): array;

    /**
     * The result of the latest interview with a sent scorecard of each application, hidden (missing) when the viewer is on that interview's panel and has not
     * sent their own scorecard. One grouped query for every application.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, InterviewResult> by application id
     */
    public function latestForApplications(array $applicationIds, int $viewerId): array;
}
