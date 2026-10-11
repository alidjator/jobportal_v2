<?php

namespace Src\Assessment\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Src\Assessment\Application\Reports\ReportDocument;
use Src\Assessment\Application\Reports\ReportPdfRenderer;

/**
 * Lays a signed report out with its Blade view (`resources/views/assessment/report.blade.php`) and turns the HTML into a PDF with dompdf. The view prints only
 * escaped text; remote files, PHP and JavaScript are off and local files are limited to the folder of the view. The font is DejaVu Sans, which dompdf ships.
 */
final readonly class DompdfReportRenderer implements ReportPdfRenderer
{
    public function __construct(private ViewFactory $views) {}

    public function render(ReportDocument $document): string
    {
        $html = $this->views->make('assessment.report', ['report' => $document])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([resource_path('views/assessment')]);
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultPaperSize('a4');
        $options->setFontCache($this->workDirectory('fonts'));
        $options->setTempDir($this->workDirectory('tmp'));

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** A folder under the framework storage that dompdf may write its font cache and temporary files to. */
    private function workDirectory(string $name): string
    {
        $path = storage_path('framework/dompdf/'.$name);

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }
}
