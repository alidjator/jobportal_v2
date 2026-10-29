<?php

namespace Src\DemoData\Application;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Src\Assessment\Application\Admin\CreatePsychologistAccount;
use Src\Assessment\Application\Admin\RegisterProvider;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;

/**
 * The partner provider of psychological batteries and its psychologists (`DemoCast::psychologists()`): the platform admin registers the provider, then each
 * psychologist account is created as the operator command does, with licences valid for five years. Each enrols a second factor at the first sign-in.
 */
final readonly class DemoProvider
{
    public function __construct(
        private RegisterProvider $registerProvider,
        private CreatePsychologistAccount $createPsychologist,
        private Clock $clock,
    ) {}

    /** @return int the account id of the lead psychologist, who writes the batteries */
    public function build(Actor $admin, string $password): int
    {
        $leadUserId = null;
        $provider = $this->registerProvider->execute($admin, DemoCast::PROVIDER_CODE, DemoCast::PROVIDER_NAME, DemoCast::PROVIDER_LEGAL_NAME, null, DemoCast::USER_AGENT);
        $validUntil = new DateTimeImmutable($this->clock->now()->modify('+5 years')->format('Y-m-d'), new DateTimeZone('UTC'));

        foreach (DemoCast::psychologists() as $index => $psychologist) {
            $number = $index + 1;

            $created = $this->createPsychologist->execute(
                (int) $provider->id, $psychologist['name'], $psychologist['email'], $password, $psychologist['role'],
                "STR-DEMO-{$number}", $validUntil, "SIPP-DEMO-{$number}", $validUntil,
            );
            $leadUserId ??= $psychologist['role']->isLead() ? $created['userId'] : null;
        }

        return $leadUserId ?? throw new LogicException('The demo has no lead psychologist.');
    }
}
