<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The optional limit on the email domains a company admin may invite to (off by default). It applies to an invitation or a resend by a company admin,
 * not to an invitation by a platform admin.
 */
final readonly class InvitationEmailDomains
{
    public const int MAX_DOMAINS = 20;

    public function __construct(private CompanyRepository $companies) {}

    /** @throws BusinessRuleViolation `email-domain-not-allowed` */
    public function assertAllowed(int $companyId, string $email): void
    {
        $domains = $this->companies->invitationEmailDomains($companyId);

        if ($domains === null) {
            return;
        }

        $domain = mb_strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        if (! in_array($domain, $domains, true)) {
            throw new BusinessRuleViolation('This company only invites email addresses of its allowed domains.', 'email-domain-not-allowed', extensions: ['allowed_domains' => $domains]);
        }
    }

    /**
     * Lower-cases, trims, strips a leading `@`, removes duplicates and sorts; an empty list means no limit (null).
     *
     * @param  list<string>  $domains
     * @return ?list<string>
     *
     * @throws BusinessRuleViolation `invalid-email-domain` for a name that is not a domain, or more than 20 of them
     */
    public static function normalized(array $domains): ?array
    {
        $clean = [];

        foreach ($domains as $domain) {
            $domain = mb_strtolower(ltrim(trim($domain), '@'));

            if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
                throw new BusinessRuleViolation('Each domain must look like example.com.', 'invalid-email-domain', extensions: ['domain' => $domain]);
            }

            $clean[$domain] = $domain;
        }

        if (count($clean) > self::MAX_DOMAINS) {
            throw new BusinessRuleViolation('At most :max domains are allowed.', 'invalid-email-domain', parameters: ['max' => self::MAX_DOMAINS]);
        }

        ksort($clean);

        return $clean === [] ? null : array_values($clean);
    }
}
