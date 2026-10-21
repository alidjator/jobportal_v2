<?php

namespace Src\DemoData\Infrastructure;

use Illuminate\Contracts\Container\Container;
use Src\Company\Application\Invitations\InvitationLinkBuilder;
use Src\Company\Application\Invitations\InvitationSender;
use Src\DemoData\Application\DemoInbox;
use Src\DemoData\Interface\Console\SeedDemoDataCommand;
use Src\Identity\Application\Auth\RegistrationThrottle;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Shared\Infrastructure\ContextServiceProvider;

/**
 * The demo data for development domains: an operational tool (one artisan command, no API) that drives the use cases of the other contexts as a client
 * does (`docs/design/phase-8-demo-data.md`). Its adapters replace the invitation and verification emails and the registration limit only for the run that
 * asks for them ({@see self::useDemoAdapters()}), never for the application.
 */
final class DemoDataServiceProvider extends ContextServiceProvider
{
    public function register(): void
    {
        $this->commands([SeedDemoDataCommand::class]);
    }

    /** Swaps the email and throttle ports for the demo adapters in this process; call it before the demo use cases are resolved. */
    public static function useDemoAdapters(Container $container): void
    {
        $inbox = new InMemoryDemoInbox;

        $container->instance(DemoInbox::class, $inbox);
        $container->instance(InvitationSender::class, $inbox);
        $container->instance(VerificationCodeSender::class, $inbox);
        $container->instance(InvitationLinkBuilder::class, new PlainTokenInvitationLink);
        $container->instance(RegistrationThrottle::class, new UnlimitedRegistrationThrottle);
    }
}
