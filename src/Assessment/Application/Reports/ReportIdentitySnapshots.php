<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\Report;
use Src\Shared\Application\FileStorage;

/**
 * What a report keeps of the institution and of its signer when a version is signed, issued automatically or revised (`docs/design/phase-8-provider-institutions.md` section 3): the institution's
 * name, address, practice licence and contact as text, and the logo and the signature image copied into the report's own folder (`assessment/reports/{report}/logo-{revision}` and
 * `signature-{revision}`), so a later change of the institution or of the psychologist never alters a report that was issued. Nothing is kept for a part the institution or the psychologist does not have.
 */
final readonly class ReportIdentitySnapshots
{
    public function __construct(private FileStorage $files) {}

    /**
     * The identity of the version, as the text that goes into the report's placeholders.
     *
     * @return array<string, mixed>
     */
    public function identityOf(Provider $provider): array
    {
        $identity = $provider->identity();

        return [
            'name' => $provider->name(),
            'legal_name' => $provider->legalName(),
            'address' => $identity->address,
            'practice_licence_number' => $identity->practiceLicenceNumber,
            'phone' => $identity->phone,
            'email' => $identity->email,
            'logo_path' => null,
        ];
    }

    /** The report with the copies of the version just signed (the report must have its id: it is a draft that was saved). */
    public function take(Report $report, Provider $provider, Psychologist $signer): Report
    {
        $folder = 'assessment/reports/'.(int) $report->id;
        $tag = (string) $report->revision;
        $identity = $this->identityOf($provider);
        $identity['logo_path'] = $this->copy($provider->identity()->logoPath, "{$folder}/logo-{$tag}");

        return $report->snapshotted($identity, $this->copy($signer->signaturePath(), "{$folder}/signature-{$tag}"));
    }

    /** The copy's path, or null when there is nothing to copy (no file, or one that has gone missing). */
    private function copy(?string $from, string $toWithoutExtension): ?string
    {
        $contents = $from === null ? null : $this->files->get($from);

        if ($from === null || $contents === null) {
            return null;
        }

        $path = $toWithoutExtension.'.'.(strtolower(pathinfo($from, PATHINFO_EXTENSION)) ?: 'png');
        $this->files->put($path, $contents);

        return $path;
    }
}
