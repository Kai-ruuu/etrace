<?php

namespace App\Core\Types;

enum WorkSetup: string
{
    case ON_SITE = 'On-site';
    case REMOTE  = 'Remote';
    case HYBRID  = 'Hybrid';
}
