<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Events\NewsletterSubscriberDeleted;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Erases a subscriber (on their request), with their deliveries. The address can join again only through the confirmation email. */
final readonly class DeleteSubscriber
{
    public function __construct(
        private SubscriberRepository $subscribers,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $subscriberId, ?string $ipAddress, ?string $userAgent): void
    {
        $adminId = NewsletterAccess::assertAdmin($actor);

        $this->transactions->run(function () use ($subscriberId): void {
            $this->subscribers->find($subscriberId, true) ?? throw EntityNotFound::of('Subscriber', $subscriberId);
            $this->subscribers->delete($subscriberId);
        });

        $this->events->dispatch([new NewsletterSubscriberDeleted($subscriberId, $adminId, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
