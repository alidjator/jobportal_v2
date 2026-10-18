<?php

namespace Src\Platform\Application\EmailSuppression;

use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Intake of a provider's webhook: the signature is checked by the provider's parser, then every hard bounce and complaint is suppressed (an address already listed is left as it is). */
final readonly class ReceiveEmailEvents
{
    public function __construct(
        private EmailEventParsers $parsers,
        private EmailSuppressionRepository $suppressions,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, string>  $headers  the request headers, names in lower case
     * @return int how many addresses were newly suppressed
     *
     * @throws EntityNotFound when no parser is registered for the provider
     * @throws AccessDenied `invalid-signature`
     */
    public function execute(string $provider, string $body, array $headers): int
    {
        $parser = $this->parsers->find($provider) ?? throw new EntityNotFound('Unknown email provider.');

        try {
            $events = $parser->parse($body, $headers);
        } catch (InvalidWebhookSignature) {
            throw new AccessDenied('The webhook signature is not valid.', 'invalid-signature');
        }

        $now = $this->clock->now();
        $added = 0;

        foreach ($events as $event) {
            $email = mb_strtolower(trim($event->email));

            if ($email !== '' && $this->suppressions->add($email, $event->reason, $provider, $event->details === null ? null : mb_substr($event->details, 0, 1000), $now) !== null) {
                $added++;
            }
        }

        return $added;
    }
}
