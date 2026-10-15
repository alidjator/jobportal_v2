<?php

namespace Src\Job\Application\Browse;

/**
 * The orders the public job search offers; the value is what the client sends as `sort`. `relevance` ranks by how well the keyword fits (the default when a
 * keyword is given); `match` ranks by the match percentage of the signed-in job seeker (see `SearchJobs`).
 */
enum JobSearchSort: string
{
    case PublishedAtDescending = '-published_at';
    case PublishedAtAscending = 'published_at';
    case DeadlineAscending = 'deadline';
    case DeadlineDescending = '-deadline';
    case Relevance = 'relevance';
    case Match = 'match';
}
