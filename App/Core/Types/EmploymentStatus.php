<?php

namespace App\Core\Types;

enum EmploymentStatus: string
{
    case UNEMPLOYED = 'Unemployed';
    case EMPLOYED   = 'Employed';
    case SELF       = 'Self-employed';
    case DECEASED   = 'Deceased';
}