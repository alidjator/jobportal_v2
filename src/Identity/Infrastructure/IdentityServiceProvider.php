<?php

namespace Src\Identity\Infrastructure;

use Google\Auth\AccessToken;
use Google\Auth\Cache\FileSystemCacheItemPool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Account\AdminAccessReader;
use Src\Identity\Application\Account\IdentityAccountActivation;
use Src\Identity\Application\Account\IdentityAdminContacts;
use Src\Identity\Application\Account\IdentityApplicantContacts;
use Src\Identity\Application\Account\IdentityCompanyMemberAccounts;
use Src\Identity\Application\Account\IdentityFirstSignIns;
use Src\Identity\Application\Account\IdentityMemberSessions;
use Src\Identity\Application\Account\IdentityMemberTwoFactor;
use Src\Identity\Application\Account\IdentityPsychologistAccounts;
use Src\Identity\Application\Account\UserDataEraser;
use Src\Identity\Application\Activation\ActivationEmailSender;
use Src\Identity\Application\Activation\ActivationLinks;
use Src\Identity\Application\Admin\AdminDirectory;
use Src\Identity\Application\Admin\AdminRbacWriter;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\GoogleIdentityVerifier;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\Auth\PasswordResetRequestThrottle;
use Src\Identity\Application\Auth\RegistrationThrottle;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Application\Auth\VerificationCodeGenerator;
use Src\Identity\Application\Auth\VerificationCodeHasher;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Application\EmailChange\EmailChangeThrottle;
use Src\Identity\Application\Erasure\ErasureLedger;
use Src\Identity\Application\IdentityEmailTemplates;
use Src\Identity\Application\IdentityPermissions;
use Src\Identity\Application\IdentitySettings;
use Src\Identity\Application\TwoFactor\RecoveryCodes;
use Src\Identity\Application\TwoFactor\TwoFactorAuthenticator;
use Src\Identity\Application\TwoFactor\TwoFactorChallenges;
use Src\Identity\Application\TwoFactor\TwoFactorNoticeSender;
use Src\Identity\Application\TwoFactor\TwoFactorTokens;
use Src\Identity\Application\TwoFactor\UserRecoveryCodes;
use Src\Identity\Application\TwoFactor\UserTwoFactorChallenges;
use Src\Identity\Application\TwoFactor\UserTwoFactors;
use Src\Identity\Domain\ActivationEmailRepository;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\EmailChangeRepository;
use Src\Identity\Domain\Events\AccountDeletionCancelled;
use Src\Identity\Domain\Events\AccountDeletionRequested;
use Src\Identity\Domain\Events\AdminPasswordReset;
use Src\Identity\Domain\Events\AdminReactivated;
use Src\Identity\Domain\Events\AdminRoleChanged;
use Src\Identity\Domain\Events\AdminRoleCreated;
use Src\Identity\Domain\Events\AdminRoleDeleted;
use Src\Identity\Domain\Events\AdminRolePermissionsChanged;
use Src\Identity\Domain\Events\AdminSuspended;
use Src\Identity\Domain\Events\PasswordChanged;
use Src\Identity\Domain\Events\PasswordReset;
use Src\Identity\Domain\Events\RecoveryCodesRegenerated;
use Src\Identity\Domain\Events\RecoveryCodeUsed;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignedOutEverywhere;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\TwoFactorEnabled;
use Src\Identity\Domain\Events\TwoFactorReset;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Domain\Events\UserRecoveryCodesRegenerated;
use Src\Identity\Domain\Events\UserRecoveryCodeUsed;
use Src\Identity\Domain\Events\UserTwoFactorDisabled;
use Src\Identity\Domain\Events\UserTwoFactorEnabled;
use Src\Identity\Domain\Events\UserTwoFactorReset;
use Src\Identity\Domain\SocialAccountRepository;
use Src\Identity\Domain\TermsAcceptanceRepository;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\VerificationCodeRepository;
use Src\Identity\Infrastructure\Auth\Google2faTwoFactorAuthenticator;
use Src\Identity\Infrastructure\Auth\GoogleAuthIdentityVerifier;
use Src\Identity\Infrastructure\Auth\HmacVerificationCodeHasher;
use Src\Identity\Infrastructure\Auth\LaravelPasswordHasher;
use Src\Identity\Infrastructure\Auth\RandomTwoFactorTokens;
use Src\Identity\Infrastructure\Auth\RandomVerificationCodeGenerator;
use Src\Identity\Infrastructure\Auth\RateLimiterEmailChangeThrottle;
use Src\Identity\Infrastructure\Auth\RateLimiterPasswordResetRequestThrottle;
use Src\Identity\Infrastructure\Auth\RateLimiterRegistrationThrottle;
use Src\Identity\Infrastructure\Auth\RateLimiterSignInThrottle;
use Src\Identity\Infrastructure\Auth\SanctumAccessTokenIssuer;
use Src\Identity\Infrastructure\Auth\SpatieAdminAccessReader;
use Src\Identity\Infrastructure\Auth\SpatieAdminDirectory;
use Src\Identity\Infrastructure\Auth\SpatieAdminRbacWriter;
use Src\Identity\Infrastructure\Dashboard\PlatformJobSeekersCard;
use Src\Identity\Infrastructure\Listeners\RecordIdentityAuditEntries;
use Src\Identity\Infrastructure\Mail\ConfigActivationLinks;
use Src\Identity\Infrastructure\Mail\MailAccountNoticeSender;
use Src\Identity\Infrastructure\Mail\MailActivationEmailSender;
use Src\Identity\Infrastructure\Mail\MailTwoFactorNoticeSender;
use Src\Identity\Infrastructure\Mail\MailVerificationCodeSender;
use Src\Identity\Infrastructure\Persistence\DatabaseActivationEmailRepository;
use Src\Identity\Infrastructure\Persistence\DatabaseEmailChangeRepository;
use Src\Identity\Infrastructure\Persistence\DatabaseErasureLedger;
use Src\Identity\Infrastructure\Persistence\DatabaseRecoveryCodes;
use Src\Identity\Infrastructure\Persistence\DatabaseTermsAcceptanceRepository;
use Src\Identity\Infrastructure\Persistence\DatabaseTwoFactorChallenges;
use Src\Identity\Infrastructure\Persistence\DatabaseUserRecoveryCodes;
use Src\Identity\Infrastructure\Persistence\DatabaseUserTwoFactorChallenges;
use Src\Identity\Infrastructure\Persistence\DatabaseUserTwoFactors;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\EloquentAdminRepository;
use Src\Identity\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use Src\Identity\Infrastructure\Persistence\Eloquent\EloquentVerificationCodeRepository;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Identity\Infrastructure\Persistence\EloquentAdminAccounts;
use Src\Identity\Infrastructure\Persistence\EloquentSocialAccountRepository;
use Src\Identity\Infrastructure\Persistence\EloquentUserDataEraser;
use Src\Identity\Infrastructure\Privacy\AccountDataExporter;
use Src\Identity\Interface\Console\AnonymizeDueAccountsCommand;
use Src\Identity\Interface\Console\CreateAdminCommand;
use Src\Identity\Interface\Console\CreateAdminRoleCommand;
use Src\Identity\Interface\Console\DeleteAdminRoleCommand;
use Src\Identity\Interface\Console\ListAdminPermissionsCommand;
use Src\Identity\Interface\Console\ListAdminRolesCommand;
use Src\Identity\Interface\Console\ListAdminsCommand;
use Src\Identity\Interface\Console\PurgeTwoFactorChallengesCommand;
use Src\Identity\Interface\Console\ReactivateAdminCommand;
use Src\Identity\Interface\Console\ReconcileErasureCommand;
use Src\Identity\Interface\Console\ResetAdminPasswordCommand;
use Src\Identity\Interface\Console\ResetAdminTwoFactorCommand;
use Src\Identity\Interface\Console\ResetUserTwoFactorCommand;
use Src\Identity\Interface\Console\SendActivationEmailsCommand;
use Src\Identity\Interface\Console\SetAdminRoleCommand;
use Src\Identity\Interface\Console\SetAdminRolePermissionsCommand;
use Src\Identity\Interface\Console\SuspendAdminCommand;
use Src\Identity\Interface\Console\SyncAdminPermissionsCommand;
use Src\Shared\Application\Accounts\AccountActivation;
use Src\Shared\Application\Accounts\AdminAccounts;
use Src\Shared\Application\Accounts\AdminContacts;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Accounts\ErasureReceipts;
use Src\Shared\Application\Accounts\FirstSignIns;
use Src\Shared\Application\Accounts\MemberSessions;
use Src\Shared\Application\Accounts\MemberTwoFactor;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class IdentityServiceProvider extends ContextServiceProvider
{
    /**
     * Short names stored in polymorphic columns (`personal_access_tokens.tokenable_type`, `notifications.notifiable_type`,
     * RBAC `model_type`) instead of class names, so other contexts can refer to an account kind without knowing Identity's classes.
     */
    public const array MORPH_MAP = [
        'user' => UserModel::class,
        'admin' => AdminModel::class,
    ];

    /** The support actions of an admin on one account (activation email, password reset code, verification code): 5 an hour per admin and account. */
    public const string SUPPORT_ACTION_LIMITER = 'admin-user-support';

    private const int SUPPORT_ACTIONS_PER_HOUR = 5;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepository::class => EloquentUserRepository::class,
        AdminRepository::class => EloquentAdminRepository::class,
        VerificationCodeRepository::class => EloquentVerificationCodeRepository::class,
        PasswordHasher::class => LaravelPasswordHasher::class,
        VerificationCodeHasher::class => HmacVerificationCodeHasher::class,
        VerificationCodeGenerator::class => RandomVerificationCodeGenerator::class,
        VerificationCodeSender::class => MailVerificationCodeSender::class,
        AccessTokenIssuer::class => SanctumAccessTokenIssuer::class,
        SignInThrottle::class => RateLimiterSignInThrottle::class,
        RegistrationThrottle::class => RateLimiterRegistrationThrottle::class,
        PasswordResetRequestThrottle::class => RateLimiterPasswordResetRequestThrottle::class,
        AdminAccessReader::class => SpatieAdminAccessReader::class,
        UserDataEraser::class => EloquentUserDataEraser::class,
        ApplicantContacts::class => IdentityApplicantContacts::class,
        FirstSignIns::class => IdentityFirstSignIns::class,
        AccountActivation::class => IdentityAccountActivation::class,
        ActivationEmailRepository::class => DatabaseActivationEmailRepository::class,
        ActivationEmailSender::class => MailActivationEmailSender::class,
        ActivationLinks::class => ConfigActivationLinks::class,
        EmailChangeRepository::class => DatabaseEmailChangeRepository::class,
        EmailChangeThrottle::class => RateLimiterEmailChangeThrottle::class,
        AccountNoticeSender::class => MailAccountNoticeSender::class,
        MemberTwoFactor::class => IdentityMemberTwoFactor::class,
        TwoFactorNoticeSender::class => MailTwoFactorNoticeSender::class,
        MemberSessions::class => IdentityMemberSessions::class,
        AdminContacts::class => IdentityAdminContacts::class,
        AdminAccounts::class => EloquentAdminAccounts::class,
        SocialAccountRepository::class => EloquentSocialAccountRepository::class,
        AdminRbacWriter::class => SpatieAdminRbacWriter::class,
        AdminDirectory::class => SpatieAdminDirectory::class,
        TermsAcceptanceRepository::class => DatabaseTermsAcceptanceRepository::class,
        CompanyMemberAccounts::class => IdentityCompanyMemberAccounts::class,
        TwoFactorTokens::class => RandomTwoFactorTokens::class,
        TwoFactorChallenges::class => DatabaseTwoFactorChallenges::class,
        RecoveryCodes::class => DatabaseRecoveryCodes::class,
        UserTwoFactors::class => DatabaseUserTwoFactors::class,
        UserTwoFactorChallenges::class => DatabaseUserTwoFactorChallenges::class,
        UserRecoveryCodes::class => DatabaseUserRecoveryCodes::class,
        PsychologistAccounts::class => IdentityPsychologistAccounts::class,
        ErasureLedger::class => DatabaseErasureLedger::class,
        ErasureReceipts::class => DatabaseErasureLedger::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...IdentityPermissions::definitions());
        });

        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(PlatformJobSeekersCard::class),
            );
        });

        $this->app->bind(GoogleIdentityVerifier::class, fn (Application $app): GoogleIdentityVerifier => new GoogleAuthIdentityVerifier(
            new AccessToken(cache: new FileSystemCacheItemPool($app->storagePath('framework/cache/google-auth'))),
            $app->make('config')->get('services.google.client_ids', []),
        ));

        $this->app->bind(TwoFactorAuthenticator::class, fn (Application $app): TwoFactorAuthenticator => new Google2faTwoFactorAuthenticator(
            (string) $app->make('config')->get('app.name'),
        ));

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...IdentityEmailTemplates::definitions());
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...IdentitySettings::definitions());
        });

        $this->commands([AnonymizeDueAccountsCommand::class, SyncAdminPermissionsCommand::class, CreateAdminCommand::class, ResetAdminTwoFactorCommand::class, ResetUserTwoFactorCommand::class, SendActivationEmailsCommand::class, PurgeTwoFactorChallengesCommand::class, ReconcileErasureCommand::class,
            ListAdminPermissionsCommand::class, ListAdminRolesCommand::class, ListAdminsCommand::class, CreateAdminRoleCommand::class, SetAdminRolePermissionsCommand::class,
            DeleteAdminRoleCommand::class, SetAdminRoleCommand::class, SuspendAdminCommand::class, ReactivateAdminCommand::class, ResetAdminPasswordCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(AnonymizeDueAccountsCommand::class)->dailyAt('02:00')->withoutOverlapping()->onOneServer();
            $schedule->command(ReconcileErasureCommand::class)->dailyAt('04:00')->withoutOverlapping()->onOneServer();
            $schedule->command(PurgeTwoFactorChallengesCommand::class)->dailyAt('02:45')->withoutOverlapping()->onOneServer();
            $schedule->command('sanctum:prune-expired', ['--hours' => 24])->dailyAt('02:30')->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        Relation::morphMap(self::MORPH_MAP);

        RateLimiter::for(self::SUPPORT_ACTION_LIMITER, fn (Request $request): Limit => Limit::perHour(self::SUPPORT_ACTIONS_PER_HOUR)->by('admin-user-support|'.$request->user()?->getAuthIdentifier().'|'.$request->route('id')));

        $this->app->tag(AccountDataExporter::class, PersonalDataExporter::class);

        foreach ([
            SignedIn::class => 'onSignedIn',
            SignInFailed::class => 'onSignInFailed',
            PasswordReset::class => 'onPasswordReset',
            PasswordChanged::class => 'onPasswordChanged',
            SignedOutEverywhere::class => 'onSignedOutEverywhere',
            TwoFactorEnabled::class => 'onTwoFactorEnabled',
            RecoveryCodeUsed::class => 'onRecoveryCodeUsed',
            RecoveryCodesRegenerated::class => 'onRecoveryCodesRegenerated',
            TwoFactorReset::class => 'onTwoFactorReset',
            UserTwoFactorEnabled::class => 'onUserTwoFactorEnabled',
            UserTwoFactorDisabled::class => 'onUserTwoFactorDisabled',
            UserRecoveryCodeUsed::class => 'onUserRecoveryCodeUsed',
            UserRecoveryCodesRegenerated::class => 'onUserRecoveryCodesRegenerated',
            UserTwoFactorReset::class => 'onUserTwoFactorReset',
            AccountDeletionRequested::class => 'onAccountDeletionRequested',
            AccountDeletionCancelled::class => 'onAccountDeletionCancelled',
            UserAnonymized::class => 'onUserAnonymized',
            AdminRoleCreated::class => 'onAdminRoleCreated',
            AdminRolePermissionsChanged::class => 'onAdminRolePermissionsChanged',
            AdminRoleDeleted::class => 'onAdminRoleDeleted',
            AdminRoleChanged::class => 'onAdminRoleChanged',
            AdminSuspended::class => 'onAdminSuspended',
            AdminReactivated::class => 'onAdminReactivated',
            AdminPasswordReset::class => 'onAdminPasswordReset',
        ] as $event => $method) {
            Event::listen($event, [RecordIdentityAuditEntries::class, $method]);
        }

        parent::boot();
    }
}
