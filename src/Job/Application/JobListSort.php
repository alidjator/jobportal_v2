<?php

namespace Src\Job\Application;

/** The orders a company can list its jobs in; the value is what the client sends as `sort`. */
enum JobListSort: string
{
    case UpdatedAtAscending = 'updated_at';
    case UpdatedAtDescending = '-updated_at';
    case CreatedAtAscending = 'created_at';
    case CreatedAtDescending = '-created_at';
    case TitleAscending = 'title';
    case TitleDescending = '-title';
    case DeadlineAscending = 'deadline';
    case DeadlineDescending = '-deadline';
}
