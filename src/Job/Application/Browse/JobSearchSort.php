<?php

namespace Src\Job\Application\Browse;

/** The orders the public job search offers; the value is what the client sends as `sort`. There is no relevance order: the keyword only filters. */
enum JobSearchSort: string
{
    case PublishedAtDescending = '-published_at';
    case PublishedAtAscending = 'published_at';
    case DeadlineAscending = 'deadline';
    case DeadlineDescending = '-deadline';
}
