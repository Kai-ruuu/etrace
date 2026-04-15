<?php

namespace App\Core\Types\Builtin;

enum Mime: string
{
    case PDF = 'application/pdf';
    case CSV = 'text/csv';
    case PNG = 'image/png';
    case JPG = 'image/jpg';
    case JPEG = 'image/jpeg';
}