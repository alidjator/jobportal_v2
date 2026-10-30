<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the JobInvitation context, registered in the shared catalog by its service provider. */
final class InvitationSettings
{
    public const string MAX_PER_DAY = 'invitations.max_per_day';

    public const string EMAIL_ENABLED = 'invitations.email_enabled';

    public const string EMAIL_CLAIM_URL = 'invitations.email_claim_url';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MAX_PER_DAY, 50, 1, 1000,
                'The most job invitations the members of one company may send per calendar day (Jakarta time).',
                overridable: true,
            ),
            SettingDefinition::boolean(
                self::EMAIL_ENABLED, false,
                'Whether a company may invite a person by email who has no account yet (a walk-in, a referral, a CV received by email). Off until the legal review (question 20) is answered.',
            ),
            SettingDefinition::text(
                self::EMAIL_CLAIM_URL, 'https://karir.satu.solutions/undangan-lamaran/{token}', 300,
                'The page a person opens from the email of an invitation by email; `{token}` is replaced by the token of the invitation.',
            ),
        ];
    }
}
