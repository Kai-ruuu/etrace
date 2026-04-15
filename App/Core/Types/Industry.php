<?php

namespace App\Core\Types;

enum Industry: string
{
    case TECHNOLOGY_IT                    = 'Technology / IT';
    case FINANCE_BANKING_INSURANCE        = 'Finance / Banking / Insurance';
    case HEALTHCARE_PHARMACEUTICALS       = 'Healthcare / Pharmaceuticals';
    case EDUCATION_RESEARCH               = 'Education / Research';
    case MANUFACTURING_INDUSTRIAL         = 'Manufacturing / Industrial';
    case RETAILE_ECOMMERCE                = 'Retail / E-commerce';
    case FOOD_AND_BEVERAGE_HOSPITALITY    = 'Food & Beverage / Hospitality';
    case TRANSPORTATION_LOGISTICS         = 'Transportation / Logistics';
    case ENERGY_UTILITIES                 = 'Energy / Utilities';
    case MEDIA_ENTERTAINMENT_ADVERTISING  = 'Media / Entertainment / Advertising';
    case GOVERNMENT_PUBLIC_SECTOR         = 'Government / Public Sector';
    case REAL_ESTATE_CONSTRUCTION         = 'Real Estate / Construction';
    case CONSULTING_PROFESSIONAL_SERVICES = 'Consulting / Professional Services';
    case NONPROFIT_NGO                    = 'Nonprofit / NGO';
    case TELECOMMUNICATIONS               = 'Telecommunications';
}