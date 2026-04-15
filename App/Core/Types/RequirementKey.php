<?php

namespace App\Core\Types;

enum RequirementKey: string
{
    case PROFILE   = "req_company_profile";
    case PERMIT    = "req_business_permit";
    case SEC       = "req_sec";
    case DTI_CDA   = "req_dti_cda";
    case REG_EST   = "req_reg_of_est";
    case CERT_DOLE = "req_cert_from_dole";
    case CERT_CASE = "req_cert_no_case";
    case REG_PJN   = "req_philjobnet_reg";
    case LOV       = "req_list_of_vacancies";
}