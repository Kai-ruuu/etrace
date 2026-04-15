<?php

namespace App\Core;

interface Middleware
{
    public static function make(mixed ...$args): static;

    public static function label(): string;

    public function run(mixed ...$args): mixed;
}