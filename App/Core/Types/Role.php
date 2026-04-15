<?php

namespace App\Core\Types;

enum Role: string
{
    case SYSTEM_ADMIN = "sysad";
    case DEAN         = "dean";
    case PESO_STAFF   = "pstaff";
    case COMPANY      = "company";
    case ALUMNI       = "alumni";
}