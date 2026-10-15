<?php

namespace Src\Shared\Interface\Http;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A CSV download: UTF-8 with a byte order mark (so Excel reads it right), comma separated, CRLF line ends, written line by line as the rows are produced.
 * The text is untrusted, so a cell that a spreadsheet would read as a formula (it starts with `=`, `+`, `-`, `@`, a tab or a carriage return) gets a leading apostrophe.
 */
final class CsvResponse
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    public static function of(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(static function () use ($headers, $rows): void {
            echo "\xEF\xBB\xBF".self::line($headers)."\r\n";

            foreach ($rows as $row) {
                echo self::line($row)."\r\n";
            }
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param  list<string>  $cells */
    private static function line(array $cells): string
    {
        return implode(',', array_map(self::cell(...), $cells));
    }

    private static function cell(string $value): string
    {
        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            $value = "'".$value;
        }

        return preg_match('/[",\r\n]/', $value) === 1 ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
