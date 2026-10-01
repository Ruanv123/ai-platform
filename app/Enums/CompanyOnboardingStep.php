<?php

namespace App\Enums;

enum CompanyOnboardingStep: string
{
    case NameAndWebsite = 'name_website';
    case ManualCompanyDetails = 'manual_company_details';
    case SiteRead = 'site_read';
    case ProposalReview = 'proposal_review';
    case Completed = 'completed';
}
