<?php

namespace App\Core\Types;

enum Link: string
{
    case PASSWORD_RESET     = "http://localhost:5173/account/reset-password/";
    case EMAIL_VERIFICATION = "http://localhost:8000/api/email-verification/";
    case LOGIN              = "http://localhost:5173";
}