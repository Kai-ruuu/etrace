<?php

namespace App\Utils;

class ArrayLogger
{
    /**
     * Logs an array as formatted JSON to the console (stdout).
     *
     * @param array  $data    The array to print.
     * @param string $label   Optional label shown above the output.
     */
    public static function log(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
 
        if ($json === false) {
            error_log('⚠  ArrayLogger: Failed to encode array — ' . json_last_error_msg());
            return;
        }
 
        foreach (explode("\n", $json) as $line) {
            error_log('  ' . $line);
        }
    }
 
}