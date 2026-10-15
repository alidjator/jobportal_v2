<?php

namespace Src\Job\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Job context, registered in the shared catalog by its service provider. The first two can be overridden per company. */
final class JobSettings
{
    public const string REQUIRE_APPROVAL = 'jobs.require_approval';

    public const string MAX_DEADLINE_DAYS = 'jobs.max_deadline_days';

    public const string REPORT_HOLD_THRESHOLD = 'jobs.report_hold_threshold';

    public const string APPROVAL_FOR_UNVERIFIED = 'jobs.approval_for_unverified';

    public const string SUSPICIOUS_PHRASES = 'jobs.suspicious_phrases';

    /** The phrases a job is flagged for until an admin changes the list (one per line, in Indonesian as the postings are). */
    public const string DEFAULT_SUSPICIOUS_PHRASES = "biaya pendaftaran\nbiaya administrasi\nbiaya pelatihan\nbiaya seragam\nuang jaminan\ndeposit\ntransfer ke rekening\nhanya via whatsapp";

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::boolean(
                self::REQUIRE_APPROVAL, false,
                'Whether a job must be approved by an admin before it goes live. When off, publishing goes live at once.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_DEADLINE_DAYS, 90, 7, 365,
                'The farthest deadline a job may have, in days from today.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::REPORT_HOLD_THRESHOLD, 3, 0, 100,
                'How many different job seekers must report a job before it is put on hold until an admin decides. 0 turns holds off.',
            ),
            SettingDefinition::boolean(
                self::APPROVAL_FOR_UNVERIFIED, true,
                'Whether the jobs of a company without the verification badge always go to review before they are live.',
            ),
            SettingDefinition::text(
                self::SUSPICIOUS_PHRASES, self::DEFAULT_SUSPICIOUS_PHRASES, 2000,
                'Phrases that flag a job and send it to review, one per line, matched whatever the letter case on word boundaries in the title and description.',
            ),
        ];
    }
}
