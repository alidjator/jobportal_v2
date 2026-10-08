<?php

namespace Src\Recruitment\Interface\Http;

use DateTimeZone;
use Illuminate\Http\Response;
use Src\Recruitment\Application\ApplicantExport;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The CSV of an applicant export: UTF-8 with a byte order mark (so Excel reads it right), comma separated, CRLF line ends. Applicant text is untrusted, so a
 * cell that a spreadsheet would read as a formula (it starts with `=`, `+`, `-`, `@`, a tab or a carriage return) gets a leading apostrophe.
 */
final class CsvDownload
{
    public static function of(ApplicantExport $export): Response
    {
        $name = 'applicants-job-'.$export->jobId.'-'.$export->exportedAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd').'.csv';
        $lines = [self::line($export->headers), ...array_map(self::line(...), $export->rows)];

        return new Response("\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n", Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name),
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
