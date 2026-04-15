<?php

namespace App\Core\Types;

enum Link: string
{
    case EMAIL_VERIFICATION = "http://localhost:8000/api/email-verification/";
    case LOGIN              = "http://localhost:5173";
}