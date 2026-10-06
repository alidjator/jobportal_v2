<?php

namespace Src\Job\Application\Admin;

/** The orders an admin can list jobs in; the value is what the client sends as `sort`. */
enum AdminJobSort: string
{
    case UpdatedAtAscending = 'updated_at';
    case UpdatedAtDescending = '-updated_at';
    case CreatedAtAscending = 'created_at';
    case CreatedAtDescending = '-created_at';
    case PublishedAtAscending = 'published_at';
    case PublishedAtDescending = '-published_at';
    case TitleAscending = 'title';
    case TitleDescending = '-title';
    case DeadlineAscending = 'deadline';
    case DeadlineDescending = '-deadline';
}
