<?php

namespace App\Core\Types;

enum WorkEmploymentType: string
{
    case FULL_TIME  = 'Full-time';
    case PART_TIME  = 'Part-time';
    case CONTRACT   = 'Contract';
    case INTERNSHIP = 'Internship';
    case FREELANCE  = 'Freelance';
}