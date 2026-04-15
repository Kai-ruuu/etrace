<?php

namespace App\Core\Types;

enum CivilStatus: string
{
    case SINGLE    = 'Single';
    case MARRIED   = 'Married';
    case WIDOWED   = 'Widowed';
    case SEPARATED = 'Separated';
}