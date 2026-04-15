<?php

namespace App\Core\Types;

enum ApprovalStatus: string
{
    case PENDING      = 'Pending';
    case APPROVED     = 'Approved';
    case FOR_REVISION = 'For Revision';
}