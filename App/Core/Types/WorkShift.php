<?php

namespace App\Core\Types;

enum WorkShift: string
{
    case DAY             = 'Day';
    case EVENING_SWING   = 'Evening / Swing';
    case NIGHT_GRAVEYARD = 'Night / Graveyard';
    case MORNING         = 'Morning';
}