<?php

namespace Src\Company\Application\Admin;

/** The orders an admin can list companies in; the value is what the client sends as `sort`. */
enum CompanyListSort: string
{
    case NameAscending = 'name';
    case NameDescending = '-name';
    case OldestFirst = 'created_at';
    case NewestFirst = '-created_at';
}
