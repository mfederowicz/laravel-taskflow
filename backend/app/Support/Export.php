<?php

namespace App\Support;

class Export
{
    /**
     * Build a CSV payload from a header row and an iterable of value rows.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function csv(array $headers, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
