<?php

namespace Src\Recruitment\Interface\Http;

use DateTimeZone;
use Src\Recruitment\Application\ApplicantExport;
use Src\Recruitment\Application\HiresExport;
use Src\Shared\Interface\Http\CsvResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The CSV of an applicant or a hires export (the format is `CsvResponse`'s: byte order mark, formula guard, written as the rows are produced). */
final class CsvDownload
{
    public static function of(ApplicantExport $export): StreamedResponse
    {
        $name = 'applicants-job-'.$export->jobId.'-'.$export->exportedAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd').'.csv';

        return CsvResponse::of($name, $export->headers, $export->rows);
    }

    public static function ofHires(HiresExport $export): StreamedResponse
    {
        return CsvResponse::of('hires-'.$export->from.'-'.$export->to.'.csv', $export->headers, $export->rows);
    }
}
