<?php

namespace Src\JobInvitation\Domain;

/** How the company met the person outside the platform. */
enum EmailInvitationChannel: string
{
    case EmailCv = 'email_cv';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case JobFair = 'job_fair';
    case Other = 'other';
}
