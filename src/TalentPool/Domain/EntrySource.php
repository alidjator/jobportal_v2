<?php

namespace Src\TalentPool\Domain;

/** Where an entry came from: a findable candidate the member found in the search, an applicant saved from their application, or a list carried over from karir.pkp by the import. */
enum EntrySource: string
{
    case Search = 'search';
    case Application = 'application';
    case Legacy = 'legacy';
}
