<?php

namespace Src\Campaign\Domain;

/** Where a campaign reaches people. The seven kinds of the legacy system, with English values. */
enum CampaignType: string
{
    case JobFair = 'job_fair';
    case EducationInstitution = 'education_institution';
    case GovernmentInstitution = 'government_institution';
    case TrainingInstitution = 'training_institution';
    case Partner = 'partner';
    case JobMarketplace = 'job_marketplace';
    case SocialMedia = 'social_media';
}
