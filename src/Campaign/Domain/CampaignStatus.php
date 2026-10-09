<?php

namespace Src\Campaign\Domain;

/** What a campaign is on a given day: `inactive` (switched off), `scheduled` (before its start), `ended` (after its end) or `active`. */
enum CampaignStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Scheduled = 'scheduled';
    case Ended = 'ended';
}
