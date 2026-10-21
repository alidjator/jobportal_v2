<?php

namespace Tests\Unit\DemoData;

use PHPUnit\Framework\TestCase;
use Src\DemoData\Domain\DemoPdf;
use Src\JobSeeker\Domain\ResumePdf;

final class DemoPdfTest extends TestCase
{
    public function test_the_resume_upload_accepts_it(): void
    {
        $pdf = DemoPdf::make(['Andi Pratama', 'Backend developer']);

        ResumePdf::check($pdf);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('(Andi Pratama) Tj', $pdf);
    }

    public function test_the_cross_reference_table_points_at_every_object(): void
    {
        $pdf = DemoPdf::make(['One line']);

        preg_match('/startxref\n(\d+)\n/', $pdf, $start);
        $this->assertStringStartsWith('xref', substr($pdf, (int) $start[1]));

        preg_match_all('/^(\d{10}) 00000 n $/m', $pdf, $offsets);
        $this->assertCount(5, $offsets[1]);

        foreach ($offsets[1] as $index => $offset) {
            $this->assertStringStartsWith(($index + 1).' 0 obj', substr($pdf, (int) $offset));
        }
    }

    public function test_parentheses_and_backslashes_are_escaped(): void
    {
        $this->assertStringContainsString('(Nomor Induk Berusaha \\(NIB\\) a\\\\b) Tj', DemoPdf::make(['Nomor Induk Berusaha (NIB) a\\b']));
    }
}
