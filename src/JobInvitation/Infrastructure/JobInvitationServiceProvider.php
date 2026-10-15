<?php

namespace Src\JobInvitation\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\JobInvitation\Application\EraseInvitations;
use Src\JobInvitation\Application\Export\InvitationsDataExporter;
use Src\JobInvitation\Application\InvitationEmailSender;
use Src\JobInvitation\Application\InvitationEmailTemplates;
use Src\JobInvitation\Application\InvitationNotificationType;
use Src\JobInvitation\Application\InvitationSettings;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\JobInvitation\Infrastructure\Dashboard\PendingInvitationsCard;
use Src\JobInvitation\Infrastructure\Listeners\EraseInvitationsWhenUserAnonymized;
use Src\JobInvitation\Infrastructure\Listeners\MarkInvitationAppliedWhenApplicationSubmitted;
use Src\JobInvitation\Infrastructure\Mail\MailInvitationSender;
use Src\JobInvitation\Infrastructure\Persistence\DatabaseInvitationRepository;
use Src\JobInvitation\Infrastructure\Persistence\DatabaseInvitedCandidates;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\JobInvitation\InvitedCandidates;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobInvitationServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        InvitationRepository::class => DatabaseInvitationRepository::class,
        InvitationEmailSender::class => MailInvitationSender::class,
        InvitedCandidates::class => DatabaseInvitedCandidates::class,
    ];

    public function register(): void
    {
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

        Event::listen(ApplicationSubmitted::class, MarkInvitationAppliedWhenApplicationSubmitted::class);
        $this->app->tag(EraseInvitations::class, ErasureStep::class);
        $this->app->tag(InvitationsDataExporter::class, PersonalDataExporter::class);
        Event::listen(UserAnonymized::class, EraseInvitationsWhenUserAnonymized::class);
    }
}
