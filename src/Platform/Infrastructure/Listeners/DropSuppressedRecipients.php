<?php

namespace Src\Platform\Infrastructure\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Infrastructure\Email\EssentialEmail;
use Symfony\Component\Mime\Address;

/**
 * Keeps non-essential mail away from suppressed addresses: they are dropped from To, Cc and Bcc, and a message left without any recipient is cancelled.
 * A message marked with `EssentialEmail` is sent as it is; the marker never leaves the server.
 */
final readonly class DropSuppressedRecipients
{
    public function __construct(private EmailSuppressions $suppressions) {}

    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;
        $headers = $message->getHeaders();

        if ($headers->has(EssentialEmail::HEADER)) {
            $headers->remove(EssentialEmail::HEADER);

            return null;
        }

        $all = [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()];
        $suppressed = array_keys($this->suppressions->reasons(array_map(fn (Address $address): string => $address->getAddress(), $all)));

        if ($suppressed === []) {
            return null;
        }

        $keep = fn (array $addresses): array => array_values(array_filter(
            $addresses,
            fn (Address $address): bool => ! in_array(mb_strtolower($address->getAddress()), $suppressed, true),
        ));

        // Only a header that had addresses is touched, so a message without Cc or Bcc does not gain an empty header.
        if ($message->getTo() !== []) {
            $message->to(...$keep($message->getTo()));
        }

        if ($message->getCc() !== []) {
            $message->cc(...$keep($message->getCc()));
        }

        if ($message->getBcc() !== []) {
            $message->bcc(...$keep($message->getBcc()));
        }

        return $message->getTo() === [] && $message->getCc() === [] && $message->getBcc() === [] ? false : null;
    }
}
