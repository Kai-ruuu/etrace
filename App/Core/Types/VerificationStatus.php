<?php

namespace App\Core\Types;

enum VerificationStatus: string
{
    case PENDING  = 'Pending';
    case VERIFIED = 'Verified';
    case REJECTED = 'Rejected';
}