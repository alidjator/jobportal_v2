<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\SubscriberRepository;
use Src\Newsletter\Domain\SubscriberStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Takes an address off the list. Repeating it, or using the link of a subscriber who was deleted, does nothing and is not an error; only a token that is not
 * genuine is refused. A pending subscriber who unsubscribes is suppressed too.
 */
final readonly class Unsubscribe
{
    public function __construct(
        private SubscriberRepository $subscribers,
        private UnsubscribeTokens $tokens,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws BusinessRuleViolation `newsletter-token-invalid` */
    public function execute(string $token): void
    {
        $id = $this->tokens->subscriberOf($token) ?? throw new BusinessRuleViolation('This unsubscribe link is not valid.', ConfirmSubscription::TOKEN_INVALID);

        $this->transactions->run(function () use ($id): void {
            $subscriber = $this->subscribers->find($id, true);

            if ($subscriber !== null && $subscriber->status !== SubscriberStatus::Unsubscribed) {
                $this->subscribers->update($subscriber->unsubscribed($this->clock->now()));
            }
        });
    }
}
