<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\ConfirmationToken;
use Src\Newsletter\Domain\Subscriber;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Newsletter\Domain\SubscriberStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * Starts a subscription (double opt-in). The caller learns nothing about the address: a new one, one waiting for its link, one that is already confirmed and one
 * that left all look the same. Only a new address, a pending one whose last email is at least 10 minutes old, or one that left gets a confirmation email.
 */
final readonly class Subscribe
{
    public function __construct(
        private SubscriberRepository $subscribers,
        private NewsletterLinks $links,
        private ConfirmationSender $sender,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws ExternalServiceUnavailable `newsletter-unavailable` */
    public function execute(string $email, string $locale): void
    {
        $this->links->assertAvailable();

        $email = mb_strtolower(trim($email));
        $token = ConfirmationToken::generate();

        $send = $this->transactions->run(function () use ($email, $locale, $token): bool {
            $now = $this->clock->now();
            $existing = $this->subscribers->findByEmail($email, true);

            if ($existing === null) {
                $stored = $this->subscribers->add(Subscriber::pending($email, $locale, $token, $now));

                // Another request added the same address a moment ago: it sends the email, this one stays silent.
                return $stored->confirmTokenHash === $token->hash();
            }

            $due = match ($existing->status) {
                SubscriberStatus::Confirmed => false,
                SubscriberStatus::Pending => $existing->mayGetAnotherConfirmationAt($now),
                SubscriberStatus::Unsubscribed => true,
            };

            if ($due) {
                $this->subscribers->update($existing->reconfirming($locale, $token, $now));
            }

            return $due;
        });

        if ($send) {
            $this->sender->send($email, $locale, $this->links->confirmation($token->plain));
        }
    }
}
