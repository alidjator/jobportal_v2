<?php

namespace Src\Platform\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Platform\Application\Portal\PortalAlerts;
use Src\Platform\Application\Portal\PortalEmailTemplates;
use Src\Platform\Application\Portal\PortalSkippedCompany;

final readonly class MailPortalAlerts implements PortalAlerts
{
    /** A Markdown hard line break: the placeholder text is escaped, so list markup would not survive. */
    private const string BREAK = "  \n";

    public function __construct(private Mailer $mailer) {}

    public function skipped(string $email, array $skipped): void
    {
        $lines = array_map(
            fn (PortalSkippedCompany $company): string => ($company->name ?? "#{$company->id}")." ({$company->reason})",
            $skipped,
        );

        $this->mailer->to($email)->queue(new OperationsMail(PortalEmailTemplates::COMPANY_MISSING, ['companies' => implode(self::BREAK, $lines)]));
    }

    public function recovered(string $email): void
    {
        $this->mailer->to($email)->queue(new OperationsMail(PortalEmailTemplates::RECOVERED, []));
    }
}
