<?php

namespace Src\Messaging\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Messaging\Application\CompanyPresence;
use Src\Messaging\Application\ConversationReader;
use Src\Messaging\Application\EraseMessagingData;
use Src\Messaging\Application\MessageReader;
use Src\Messaging\Application\MessagingEmailTemplates;
use Src\Messaging\Application\MessagingNotificationType;
use Src\Messaging\Application\MessagingSettings;
use Src\Messaging\Application\UnreadEmailSender;
use Src\Messaging\Domain\AttachmentRepository;
use Src\Messaging\Domain\ConversationRepository;
use Src\Messaging\Domain\Events\MessageDeleted;
use Src\Messaging\Domain\Events\MessageSent;
use Src\Messaging\Domain\Events\MessagesRead;
use Src\Messaging\Domain\MessageRepository;
use Src\Messaging\Domain\MessagingDataEraser;
use Src\Messaging\Domain\ReadStateRepository;
use Src\Messaging\Infrastructure\Dashboard\UnreadConversationsCard;
use Src\Messaging\Infrastructure\Listeners\BroadcastMessagingEvents;
use Src\Messaging\Infrastructure\Listeners\EraseMessagingDataWhenUserAnonymized;
use Src\Messaging\Infrastructure\Listeners\NotifyOfMessageListener;
use Src\Messaging\Infrastructure\Mail\MailUnreadSender;
use Src\Messaging\Infrastructure\Persistence\DatabaseAttachmentRepository;
use Src\Messaging\Infrastructure\Persistence\DatabaseConversationReader;
use Src\Messaging\Infrastructure\Persistence\DatabaseConversationRepository;
use Src\Messaging\Infrastructure\Persistence\DatabaseMessageReader;
use Src\Messaging\Infrastructure\Persistence\DatabaseMessageRepository;
use Src\Messaging\Infrastructure\Persistence\DatabaseMessagingDataEraser;
use Src\Messaging\Infrastructure\Persistence\DatabaseReadStateRepository;
use Src\Messaging\Infrastructure\Presence\ReverbCompanyPresence;
use Src\Messaging\Interface\Broadcasting\MessagingChannels;
use Src\Messaging\Interface\Console\SendUnreadMessageEmailsCommand;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class MessagingServiceProvider extends ContextServiceProvider
{
    /** The name of the route limiter of sending: 60 sends per member per minute. */
    public const string SEND_LIMITER = 'chat-send';

    private const int SENDS_PER_MINUTE = 60;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        ConversationRepository::class => DatabaseConversationRepository::class,
        MessageRepository::class => DatabaseMessageRepository::class,
        AttachmentRepository::class => DatabaseAttachmentRepository::class,
        ReadStateRepository::class => DatabaseReadStateRepository::class,
        MessagingDataEraser::class => DatabaseMessagingDataEraser::class,
        ConversationReader::class => DatabaseConversationReader::class,
        MessageReader::class => DatabaseMessageReader::class,
        CompanyPresence::class => ReverbCompanyPresence::class,
        UnreadEmailSender::class => MailUnreadSender::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                new UnreadConversationsCard($this->app->make(ConnectionInterface::class), DashboardAudience::JobSeeker),
                new UnreadConversationsCard($this->app->make(ConnectionInterface::class), DashboardAudience::Company),
            );
        });

        $this->commands([SendUnreadMessageEmailsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendUnreadMessageEmailsCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...MessagingSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...MessagingEmailTemplates::definitions());
        });

        // Email only: in-app notifications are always written. The email is the one reminder for an unread conversation.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                MessagingNotificationType::UNREAD_PREFERENCE,
                'Email when a chat message is still unread 15 minutes after it arrived (one email per conversation).',
                [NotificationChannel::Email->value => true],
            ));
        });
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for(self::SEND_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::SENDS_PER_MINUTE)->by('chat-send|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // The route a client calls to join a private or presence channel; it answers 401 without a valid token, like every other route of this API.
        Broadcast::routes(['prefix' => self::API_PREFIX, 'middleware' => ['api', 'auth:user']]);
        MessagingChannels::register();

        Event::listen(MessageSent::class, NotifyOfMessageListener::class);
        Event::listen(MessageSent::class, [BroadcastMessagingEvents::class, 'sent']);
        Event::listen(MessageDeleted::class, [BroadcastMessagingEvents::class, 'deleted']);
        Event::listen(MessagesRead::class, [BroadcastMessagingEvents::class, 'read']);
        $this->app->tag(EraseMessagingData::class, ErasureStep::class);
        Event::listen(UserAnonymized::class, EraseMessagingDataWhenUserAnonymized::class);
    }
}
