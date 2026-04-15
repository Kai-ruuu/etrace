<?php

namespace App\Core\Types;

enum CvReviewStatus: string
{
    case PENDING      = 'Pending';
    case REVIEWED     = 'Reviewed';
}