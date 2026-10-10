<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\ConfirmationToken;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Confirms the address behind a link. A token that is wrong, already used or older than 3 days is refused with the same error. */
final readonly class ConfirmSubscription
{
    public const string TOKEN_INVALID = 'newsletter-token-invalid';

    public function __construct(
        private SubscriberRepository $subscribers,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws BusinessRuleViolation `newsletter-token-invalid` */
    public function execute(string $token): void
    {
        $this->transactions->run(function () use ($token): void {
            $now = $this->clock->now();
            $subscriber = $this->subscribers->findByConfirmTokenHash(ConfirmationToken::fromPlain($token)->hash(), true);

            if ($subscriber === null || ! $subscriber->canBeConfirmedAt($now)) {
                throw new BusinessRuleViolation('This confirmation link is not valid or has expired.', self::TOKEN_INVALID);
            }

            $this->subscribers->update($subscriber->confirmed($now));
        });
    }
}
