<?php

namespace Src\Recruitment\Interface\Http;

use DateTimeZone;
use Src\Recruitment\Application\ApplicantExport;
use Src\Shared\Interface\Http\CsvResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The CSV of an applicant export (the format is `CsvResponse`'s: byte order mark, formula guard, written as the rows are produced). */
final class CsvDownload
{
    public static function of(ApplicantExport $export): StreamedResponse
    {
        $name = 'applicants-job-'.$export->jobId.'-'.$export->exportedAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd').'.csv';

        return CsvResponse::of($name, $export->headers, $export->rows);
    }
}
