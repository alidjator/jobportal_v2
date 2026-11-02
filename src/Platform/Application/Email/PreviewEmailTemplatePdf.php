<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The preview of an email template (see `PreviewEmailTemplate`) as a PDF: the subject on top, then the email as it is laid out. Nothing is saved or sent. */
final readonly class PreviewEmailTemplatePdf
{
    public function __construct(
        private PreviewEmailTemplate $preview,
        private EmailPdfRenderer $pdf,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-email-template` for a draft that breaks the rules, `unsupported-language`
     */
    public function execute(string $key, string $locale, ?string $draftSubject, ?string $draftBody): string
    {
        $rendered = $this->preview->execute($key, $locale, $draftSubject, $draftBody);

        return $this->pdf->render($rendered->subject, $rendered->html);
    }
}
