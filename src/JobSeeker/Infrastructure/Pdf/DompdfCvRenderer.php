<?php

namespace Src\JobSeeker\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Src\JobSeeker\Application\Cv\CvDocument;
use Src\JobSeeker\Application\Cv\CvRenderer;

/**
 * Lays a CV out with its Blade view (`resources/views/cv/<template>.blade.php`) and turns the HTML into a PDF with dompdf. The templates print only escaped
 * text and an embedded picture: remote files, PHP and JavaScript are off and local files are limited to the folder of the views, so a profile cannot make the
 * renderer fetch a file or run code. The font is DejaVu Sans, which dompdf ships (Latin letters with accents; other scripts are not covered).
 */
final readonly class DompdfCvRenderer implements CvRenderer
{
    public function __construct(private ViewFactory $views) {}

    public function render(CvDocument $document): string
    {
        $html = $this->views->make('cv.'.$document->template->value, ['cv' => $document])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([resource_path('views/cv')]);
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
