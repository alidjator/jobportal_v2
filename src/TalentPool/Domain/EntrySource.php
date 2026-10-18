<?php

namespace Src\TalentPool\Domain;

/** Where an entry came from: a findable candidate the member found in the search, or an applicant saved from their application. */
enum EntrySource: string
{
    case Search = 'search';
    case Application = 'application';
}
