<?php

namespace Src\Company\Application\Messages;

use Src\Company\Domain\MessageDefaultsRepository;
use Src\Company\Domain\MessageLinkRule;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final readonly class CompanyCandidateMessageRules implements CandidateMessageRules
{
    public function __construct(private MessageDefaultsRepository $defaults) {}

    public function assertAllowed(int $companyId, ?string $text, string $field = 'note'): void
    {
        if ($text === null || trim($text) === '') {
            return;
        }

        $link = MessageLinkRule::foreignLink($text, $this->defaults->websiteDomain($companyId));

        if ($link !== null) {
            throw new BusinessRuleViolation(
                'A message to a candidate cannot carry a link or an email address, except one of your own website.',
                'message-link-not-allowed',
                extensions: ['errors' => [$field => ['Remove this link or address: :link']]],
                parameters: ['link' => mb_substr($link, 0, 60)],
            );
        }
    }
}
