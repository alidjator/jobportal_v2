<?php

namespace Src\Dashboard\Interface\Http;

use Illuminate\Http\Response;
use Src\Dashboard\Application\AnalyticsReportView;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * One table of an analysis as CSV: UTF-8 with a byte order mark (so Excel reads it right), comma separated, CRLF line ends, a dot as the decimal mark. The columns are the
 * fields of the JSON table. A hidden small group shows its count as `<N`; a text that a spreadsheet would read as a formula gets a leading apostrophe.
 */
final class AnalyticsCsv
{
    /** @throws EntityNotFound the analysis has no such table */
    public static function of(AnalyticsReportView $view, ?string $table): Response
    {
        $found = null;

        foreach ($view->tables as $candidate) {
            if ($table === null || $candidate['name'] === $table) {
                $found = $candidate;
                break;
            }
        }

        $found ?? throw EntityNotFound::of('Table', $table);

        $lines = [self::line($found['columns'])];

        foreach ($found['rows'] as $row) {
            $cells = [];

            foreach ($found['columns'] as $column) {
                $cells[] = $row['suppressed'] && $column === $found['count_column'] ? '<'.$view->minGroup : self::text($row[$column]);
            }

            $lines[] = self::line($cells);
        }

        $name = "analytics-{$view->report}-{$found['name']}-{$view->from}-{$view->to}.csv";

        return new Response("\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n", Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_float($value) => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'),
            default => (string) $value,
        };
    }

    /** @param  list<string>  $cells */
    private static function line(array $cells): string
    {
        return implode(',', array_map(self::cell(...), $cells));
    }

    private static function cell(string $value): string
    {
        if ($value !== '' && str_contains("=+-@\t\r", $value[0]) && ! is_numeric($value)) {
            $value = "'".$value;
        }

        return preg_match('/[",\r\n]/', $value) === 1 ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
