<?php

namespace Src\Shared\Application\Notifications;

use InvalidArgumentException;
use Src\Shared\Application\ActorKind;

/**
 * One kind of notification a user can tune, for example `application_update`. `defaults` lists the channels the kind supports and
 * whether each one is on until the user decides otherwise; a channel that is not listed is not offered for this kind. `audiences`
 * names the kinds of account that see and may change it (a job seeker by default).
 */
final readonly class NotificationPreferenceDefinition
{
    /**
     * @param  array<string, bool>  $defaults  channel value (`email`, `push`) => on by default
     * @param  list<string>  $audiences  `job_seeker`, `company_member` or `psychologist`
     */
    public function __construct(
        public string $key,
        public string $description,
        public array $defaults,
        public array $audiences = [ActorKind::JobSeeker->value],
    ) {
        if ($defaults === []) {
            throw new InvalidArgumentException("Notification preference {$key} must support at least one channel.");
        }

        foreach (array_keys($defaults) as $channel) {
            if (NotificationChannel::tryFrom((string) $channel) === null) {
                throw new InvalidArgumentException("Notification preference {$key} lists the unknown channel {$channel}.");
            }
        }

        if ($audiences === []) {
            throw new InvalidArgumentException("Notification preference {$key} must have at least one audience.");
        }

        foreach ($audiences as $audience) {
            if (! in_array($audience, [ActorKind::JobSeeker->value, ActorKind::CompanyMember->value, ActorKind::Psychologist->value], true)) {
                throw new InvalidArgumentException("Notification preference {$key} lists the unknown audience {$audience}.");
            }
        }
    }

    public function supports(NotificationChannel $channel): bool
    {
        return array_key_exists($channel->value, $this->defaults);
    }

    public function isFor(ActorKind $kind): bool
    {
        return in_array($kind->value, $this->audiences, true);
    }

    public function defaultFor(NotificationChannel $channel): bool
    {
        return $this->defaults[$channel->value] ?? false;
    }
}
