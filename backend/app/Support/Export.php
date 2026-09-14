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
            fputcsv($stream, array_map([self::class, 'sanitizeCell'], $row));
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * Guard against CSV formula injection (OWASP): a cell starting with `=`,
     * `+`, `-`, or `@` is interpreted as a formula when the file is opened in
     * Excel or Google Sheets. Prefixing the single quote renders it as text.
     */
    private static function sanitizeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && str_contains('=+-@', $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
