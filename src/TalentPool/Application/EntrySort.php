<?php

namespace Src\TalentPool\Application;

/** How the talent pool list is ordered: by the day it was saved, or by the latest application or invitation of the person (both newest first). */
enum EntrySort: string
{
    case SavedAt = 'saved_at';
    case LastActivity = 'last_activity';
}
