<?php

namespace Src\Matching\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Matching\Application\EraseRecommendations;
use Src\Matching\Application\MatchScorer;
use Src\Matching\Application\RecommendationEmailSender;
use Src\Matching\Application\RecommendationEmailTemplates;
use Src\Matching\Application\RecommendationNotificationType;
use Src\Matching\Application\RecommendationQueue;
use Src\Matching\Application\ScoreApplicant;
use Src\Matching\Domain\DeliveryRepository;
use Src\Matching\Infrastructure\Listeners\EraseRecommendationsWhenUserAnonymized;
use Src\Matching\Infrastructure\Mail\MailRecommendationSender;
use Src\Matching\Infrastructure\Persistence\DatabaseDeliveryRepository;
use Src\Matching\Interface\Console\SendRecommendationsCommand;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Matching\ApplicantMatchScores;
use Src\Shared\Application\Matching\MatchScoring;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class MatchingServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DeliveryRepository::class => DatabaseDeliveryRepository::class,
        RecommendationEmailSender::class => MailRecommendationSender::class,
        RecommendationQueue::class => LaravelRecommendationQueue::class,
        MatchScoring::class => MatchScorer::class,
        ApplicantMatchScores::class => ScoreApplicant::class,
    ];

    public function register(): void
    {
        $this->commands([SendRecommendationsCommand::class]);

        // Every Monday at 08:00 Jakarta time. The use case leaves out anyone who was sent an email in the last six days, so a second run does no harm.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendRecommendationsCommand::class)->weeklyOn(1, '08:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...RecommendationEmailTemplates::definitions());
        });

        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                RecommendationNotificationType::EMAIL_PREFERENCE,
                'Email once a week with jobs that fit your profile.',
                [NotificationChannel::Email->value => true],
                scheduled: true,
            ));
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->tag(EraseRecommendations::class, ErasureStep::class);

        Event::listen(UserAnonymized::class, EraseRecommendationsWhenUserAnonymized::class);
    }
}
