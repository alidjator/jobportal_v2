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
     * The latest interview with a sent scorecard of each application (newest created first) and what the viewer may see of its result. One grouped query for
     * every application; an application without such an interview is missing.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, LatestInterview> by application id
     */
    public function latestInterviews(array $applicationIds, int $viewerId): array;

    /**
     * What the sent scorecards gave each question of these interviews, in the order of the questions. It checks nothing about who asks: the caller leaves out the
     * interviews the viewer may not see.
     *
     * @param  list<int>  $interviewIds
     * @return array<int, list<QuestionScore>> by interview id
     */
    public function questionScores(array $interviewIds): array;

    /**
     * The result of the latest interview with a sent scorecard of each application, hidden (missing) when the viewer is on that interview's panel and has not
     * sent their own scorecard. One grouped query for every application.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, InterviewResult> by application id
     */
    public function latestForApplications(array $applicationIds, int $viewerId): array;
}
