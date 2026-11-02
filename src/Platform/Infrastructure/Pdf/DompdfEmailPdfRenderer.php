<?php

namespace Src\Platform\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Src\Platform\Application\Email\EmailPdfRenderer;

/**
 * Prints the HTML of a rendered email with dompdf, with the subject as a line above it and as the title of the file. Remote files, PHP and JavaScript are off and
 * local files are limited to the views folder; the HTML comes from the email renderer, which escapes raw HTML in the body. The font is DejaVu Sans, which dompdf ships.
 */
final class DompdfEmailPdfRenderer implements EmailPdfRenderer
{
    public function render(string $subject, string $html): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([resource_path('views')]);
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultPaperSize('a4');
        $options->setFontCache($this->workDirectory('fonts'));
        $options->setTempDir($this->workDirectory('tmp'));

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->withSubject($subject, $html), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->addInfo('Title', $subject);

        return (string) $pdf->output();
    }

    /** The subject goes right after the opening `<body>` tag, or in front of the HTML when it has none. */
    private function withSubject(string $subject, string $html): string
    {
        $line = '<p><strong>'.htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</strong></p><hr>';
        $injected = preg_replace('/<body\b[^>]*>/i', '$0'.$line, $html, 1, $count);

        return $count === 1 ? (string) $injected : $line.$html;
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
