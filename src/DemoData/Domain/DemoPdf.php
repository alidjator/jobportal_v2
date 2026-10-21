<?php

namespace Src\DemoData\Domain;

/**
 * A one-page PDF with a few lines of text, for the demo resumes and company documents: small, unencrypted and without scripts, so the upload checks
 * accept it and a viewer shows it.
 */
final class DemoPdf
{
    /** @param  list<string>  $lines  plain ASCII text, one line each */
    public static function make(array $lines): string
    {
        $text = "BT\n/F1 12 Tf\n72 770 Td\n16 TL\n";

        foreach ($lines as $line) {
            $text .= '('.self::escape($line).") Tj T*\n";
        }

        $text .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($text)." >>\nstream\n".$text.'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n".'<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private static function escape(string $line): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
    }
}
