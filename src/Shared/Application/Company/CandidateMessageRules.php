<?php

namespace Src\Shared\Application\Company;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The rule every free text a company sends to a candidate follows (the note of an interview, of a test invitation and of an offer, and the default texts that
 * prefill them): no link and no email address, except one of the company's own domain. Provided by Company, which knows the company's website.
 */
interface CandidateMessageRules
{
    /**
     * @param  string  $field  the request field the error names
     *
     * @throws BusinessRuleViolation `message-link-not-allowed`
     */
    public function assertAllowed(int $companyId, ?string $text, string $field = 'note'): void;
}
