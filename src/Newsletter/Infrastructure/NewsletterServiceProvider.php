<?php

namespace Src\Newsletter\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Newsletter\Application\ConfirmationSender;
use Src\Newsletter\Application\NewsletterEmailTemplates;
use Src\Newsletter\Application\NewsletterLinks;
use Src\Newsletter\Application\NewsletterMailer;
use Src\Newsletter\Application\NewsletterPermissions;
use Src\Newsletter\Application\NewsletterQueue;
use Src\Newsletter\Application\UnsubscribeTokens;
use Src\Newsletter\Domain\DeliveryRepository;
use Src\Newsletter\Domain\Events\NewsletterIssueSent;
use Src\Newsletter\Domain\Events\NewsletterSubscriberDeleted;
use Src\Newsletter\Domain\IssueRepository;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Newsletter\Infrastructure\Listeners\RecordNewsletterAuditEntries;
use Src\Newsletter\Infrastructure\Mail\MailConfirmationSender;
use Src\Newsletter\Infrastructure\Mail\MailNewsletterMailer;
use Src\Newsletter\Infrastructure\Persistence\DatabaseDeliveryRepository;
use Src\Newsletter\Infrastructure\Persistence\DatabaseIssueRepository;
use Src\Newsletter\Infrastructure\Persistence\DatabaseSubscriberRepository;
use Src\Newsletter\Interface\Console\PurgePendingSubscribersCommand;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class NewsletterServiceProvider extends ContextServiceProvider
{
    /** The limiters of the public routes: subscribing is slow on purpose (it sends an email), the others are plain reads of a token. */
    public const string SUBSCRIBE_LIMITER = 'newsletter-subscribe';

    public const string TOKEN_LIMITER = 'newsletter-token';

    private const int SUBSCRIBE_PER_MINUTE = 5;

    private const int SUBSCRIBE_PER_ADDRESS_PER_HOUR = 3;

    private const int TOKEN_PER_MINUTE = 30;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        SubscriberRepository::class => DatabaseSubscriberRepository::class,
        IssueRepository::class => DatabaseIssueRepository::class,
        DeliveryRepository::class => DatabaseDeliveryRepository::class,
        NewsletterLinks::class => ConfiguredNewsletterLinks::class,
        UnsubscribeTokens::class => HmacUnsubscribeTokens::class,
        ConfirmationSender::class => MailConfirmationSender::class,
        NewsletterMailer::class => MailNewsletterMailer::class,
        NewsletterQueue::class => LaravelNewsletterQueue::class,
    ];

    public function register(): void
    {
        $this->commands([PurgePendingSubscribersCommand::class]);

        // Once a day at 03:00 Jakarta time.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgePendingSubscribersCommand::class)->dailyAt('03:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...NewsletterPermissions::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...NewsletterEmailTemplates::definitions());
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::SUBSCRIBE_LIMITER, function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(self::SUBSCRIBE_PER_MINUTE)->by('newsletter-subscribe|'.$request->ip()),
                Limit::perHour(self::SUBSCRIBE_PER_ADDRESS_PER_HOUR)->by('newsletter-subscribe-address|'.hash('sha256', $email)),
            ];
        });
        RateLimiter::for(self::TOKEN_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::TOKEN_PER_MINUTE)->by('newsletter-token|'.$request->ip()));

        Event::listen(NewsletterIssueSent::class, [RecordNewsletterAuditEntries::class, 'onNewsletterIssueSent']);
        Event::listen(NewsletterSubscriberDeleted::class, [RecordNewsletterAuditEntries::class, 'onNewsletterSubscriberDeleted']);

        parent::boot();
    }
}
