<?php

namespace Src\Recruitment\Application;

use DateInterval;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\OfferStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/**
 * Runs every hour: marks pending offers past their deadline as expired (the application stays in its stage and the team may send a new offer), and warns the
 * job seeker once about every pending offer whose deadline falls within the next 24 hours.
 */
final readonly class ProcessOfferDeadlines
{
    public const int HOURS_AHEAD = 24;

    public function __construct(
        private OfferRepository $offers,
        private ApplicationRepository $applications,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return array{expired: int, reminded: int} */
    public function execute(): array
    {
        $now = $this->clock->now();
        $expired = 0;
        $reminded = 0;

        foreach ($this->offers->dueForExpiry($now) as $due) {
            $done = $this->transactions->run(function () use ($due, $now): bool {
                $offer = $this->offers->active($due->applicationId, forUpdate: true);

                if (! $offer instanceof Offer || $offer->id !== $due->id || $offer->expiresAt > $now) {
                    return false;
                }

                $this->offers->update($offer->expire());

                return true;
            });

            $expired += $done ? 1 : 0;
        }

        foreach ($this->offers->dueForReminder($now, $now->add(new DateInterval('PT'.self::HOURS_AHEAD.'H'))) as $due) {
            $event = $this->transactions->run(function () use ($due, $now): ?OfferChanged {
                $offer = $this->offers->active($due->applicationId, forUpdate: true);

                if (! $offer instanceof Offer || $offer->id !== $due->id || $offer->status !== OfferStatus::Pending || $offer->remindedAt !== null || $offer->expiresAt <= $now) {
                    return null;
                }

                $application = $this->applications->find($offer->applicationId);

                if ($application === null) {
                    return null;
                }

                $this->offers->update($offer->reminded($now));

                return OfferChanged::of(OfferChange::Expiring, $application, $offer, $now);
            });

            if ($event instanceof OfferChanged) {
                $this->events->dispatch([$event]);
                $reminded++;
            }
        }

        return ['expired' => $expired, 'reminded' => $reminded];
    }
}
