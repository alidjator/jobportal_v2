<?php

namespace Src\Recruitment\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Src\Recruitment\Application\OfferLetterDocument;
use Src\Recruitment\Application\OfferLetterRenderer;

/**
 * Lays an offer letter out with its Blade view (`resources/views/recruitment/offer-letter.blade.php`) and turns the HTML into an A4 PDF with dompdf. The view prints
 * only escaped text; remote files, PHP and JavaScript are off and local files are limited to the folder of the view (the logo comes as a `data:` URI). The font is
 * DejaVu Sans, which dompdf ships.
 */
final readonly class DompdfOfferLetterRenderer implements OfferLetterRenderer
{
    public function __construct(private ViewFactory $views) {}

    public function render(OfferLetterDocument $document): string
    {
        $html = $this->views->make('recruitment.offer-letter', ['letter' => $document])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([resource_path('views/recruitment')]);
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
