<?php

namespace Src\Recruitment\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Recruitment\Application\ApplicantApplicationReader;
use Src\Recruitment\Application\ApplicationNotificationType;
use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\ProvisionDefaultStages;
use Src\Recruitment\Application\RecruiterApplicationReader;
use Src\Recruitment\Application\RecruitmentEmailTemplates;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\ScreeningQuestionReader;
use Src\Recruitment\Application\ScreeningQuestionUsage;
use Src\Recruitment\Application\StageChangeEmailSender;
use Src\Recruitment\Domain\ApplicantDataEraser;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Infrastructure\Listeners\EraseApplicantDataWhenUserAnonymized;
use Src\Recruitment\Infrastructure\Listeners\NotifyApplicantOfStageChangeListener;
use Src\Recruitment\Infrastructure\Listeners\NotifyAssigneesOfApplication;
use Src\Recruitment\Infrastructure\Mail\MailStageChangeSender;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantApplicationReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantDataEraser;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationUsage;
use Src\Recruitment\Infrastructure\Persistence\DatabaseJobQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseRecruiterApplicationReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseStageRepository;
use Src\Recruitment\Interface\Console\ProvisionDefaultStagesCommand;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\Recruitment\JobApplications;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class RecruitmentServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        StageRepository::class => DatabaseStageRepository::class,
        DefaultStageProvisioner::class => ProvisionDefaultStages::class,
        ScreeningQuestionRepository::class => DatabaseScreeningQuestionRepository::class,
        ScreeningQuestionReader::class => DatabaseScreeningQuestionReader::class,
        ScreeningQuestionUsage::class => DatabaseApplicationUsage::class,
        JobApplicationCheck::class => DatabaseApplicationUsage::class,
        JobApplications::class => DatabaseApplicationUsage::class,
        ApplicationRepository::class => DatabaseApplicationRepository::class,
        ApplicantDataEraser::class => DatabaseApplicantDataEraser::class,
        StageChangeEmailSender::class => MailStageChangeSender::class,
        ApplicantApplicationReader::class => DatabaseApplicantApplicationReader::class,
        RecruiterApplicationReader::class => DatabaseRecruiterApplicationReader::class,
        JobQuestionRepository::class => DatabaseJobQuestionRepository::class,
    ];

    public function register(): void
    {
        $this->commands([ProvisionDefaultStagesCommand::class]);

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...RecruitmentSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...RecruitmentEmailTemplates::definitions());
        });

        // Email only: push joins when push can be sent. The applicant's in-app notifications are always written.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                ApplicationNotificationType::UPDATE_PREFERENCE,
                'Email when the stage of one of your applications changes (interview, offer, hired or rejected).',
                [NotificationChannel::Email->value => true],
            ));
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(ApplicationSubmitted::class, NotifyAssigneesOfApplication::class);
        Event::listen(ApplicationStageChanged::class, [NotifyApplicantOfStageChangeListener::class, 'inApp']);
        Event::listen(ApplicationStageChanged::class, [NotifyApplicantOfStageChangeListener::class, 'email']);
        Event::listen(UserAnonymized::class, EraseApplicantDataWhenUserAnonymized::class);
    }
}
