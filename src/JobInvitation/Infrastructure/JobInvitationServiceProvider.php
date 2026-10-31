<?php

namespace Src\JobInvitation\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\JobInvitation\Application\EmailInvitationEmailSender;
use Src\JobInvitation\Application\EraseInvitations;
use Src\JobInvitation\Application\Export\InvitationsDataExporter;
use Src\JobInvitation\Application\InvitationEmailSender;
use Src\JobInvitation\Application\InvitationEmailTemplates;
use Src\JobInvitation\Application\InvitationNotificationType;
use Src\JobInvitation\Application\InvitationSettings;
use Src\JobInvitation\Application\LatestInvitationsOfCompany;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\JobInvitation\Infrastructure\Dashboard\PendingInvitationsCard;
use Src\JobInvitation\Infrastructure\Listeners\EraseInvitationsWhenUserAnonymized;
use Src\JobInvitation\Infrastructure\Listeners\MarkEmailInvitationAppliedWhenApplicationSubmitted;
use Src\JobInvitation\Infrastructure\Listeners\MarkInvitationAppliedWhenApplicationSubmitted;
use Src\JobInvitation\Infrastructure\Mail\MailEmailInvitationSender;
use Src\JobInvitation\Infrastructure\Mail\MailInvitationSender;
use Src\JobInvitation\Infrastructure\Persistence\DatabaseEmailInvitationRepository;
use Src\JobInvitation\Infrastructure\Persistence\DatabaseInvitationRepository;
use Src\JobInvitation\Infrastructure\Persistence\DatabaseInvitedCandidates;
use Src\JobInvitation\Infrastructure\Tasks\ApplicantInvitationTasks;
use Src\JobInvitation\Interface\Console\PurgeEmailInvitationsCommand;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\JobInvitation\InvitedCandidates;
use Src\Shared\Application\JobInvitation\LatestInvitations;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Tasks\ApplicantTaskSources;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobInvitationServiceProvider extends ContextServiceProvider
{
    /** The limiter of the person's link of an invitation by email: reading a token and answering it, per address. */
    public const string TOKEN_LIMITER = 'email-invitation-token';

    private const int TOKEN_PER_MINUTE = 20;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        InvitationRepository::class => DatabaseInvitationRepository::class,
        EmailInvitationRepository::class => DatabaseEmailInvitationRepository::class,
        EmailInvitationEmailSender::class => MailEmailInvitationSender::class,
        InvitationEmailSender::class => MailInvitationSender::class,
        InvitedCandidates::class => DatabaseInvitedCandidates::class,
        LatestInvitations::class => LatestInvitationsOfCompany::class,
    ];

    public function register(): void
    {
        $this->commands([PurgeEmailInvitationsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeEmailInvitationsCommand::class)->dailyAt('04:00')->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(ApplicantTaskSources::class, function (ApplicantTaskSources $sources): void {
            $sources->add($this->app->make(ApplicantInvitationTasks::class));
        });

        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(PendingInvitationsCard::class),
            );
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...InvitationSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...InvitationEmailTemplates::definitions());
        });

        // Email only: in-app notifications are always written.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                InvitationNotificationType::EMAIL_PREFERENCE,
                'Email when a company invites you to apply to a job.',
                [NotificationChannel::Email->value => true],
            ));
        });
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for(self::TOKEN_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::TOKEN_PER_MINUTE)->by('email-invitation-token|'.$request->ip()));

        Event::listen(ApplicationSubmitted::class, MarkInvitationAppliedWhenApplicationSubmitted::class);
        Event::listen(ApplicationSubmitted::class, MarkEmailInvitationAppliedWhenApplicationSubmitted::class);
        $this->app->tag(EraseInvitations::class, ErasureStep::class);
        $this->app->tag(InvitationsDataExporter::class, PersonalDataExporter::class);
        Event::listen(UserAnonymized::class, EraseInvitationsWhenUserAnonymized::class);
    }
}
