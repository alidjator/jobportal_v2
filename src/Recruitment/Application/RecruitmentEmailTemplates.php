<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/**
 * The email templates of the Recruitment context, registered in the shared catalog by its service provider: one per outcome the applicant is told about.
 * Bodies are Markdown with `{{variable}}` placeholders. They never carry the rejection reason or note. Only the receipt of an application carries a link (to the application page, when the client page is configured).
 */
final class RecruitmentEmailTemplates
{
    public const string RECEIVED = 'recruitment.application_received';

    public const string INTERVIEW = 'recruitment.application_interview';

    public const string OFFER = 'recruitment.application_offer';

    public const string HIRED = 'recruitment.application_hired';

    public const string REJECTED = 'recruitment.application_rejected';

    public const string INTERVIEW_SCHEDULED = 'recruitment.interview_scheduled';

    public const string INTERVIEW_RESCHEDULED = 'recruitment.interview_rescheduled';

    public const string INTERVIEW_CANCELLED = 'recruitment.interview_cancelled';

    public const string INTERVIEW_UPDATED = 'recruitment.interview_updated';

    public const string INTERVIEW_DECLINED = 'recruitment.interview_declined';

    public const string INTERVIEW_REMINDER = 'recruitment.interview_reminder';

    /** Sent to the job seeker when the team marked the application withdrawn; carries the `interview.ics` cancel when an interview was active. */
    public const string APPLICATION_WITHDRAWN_BY_COMPANY = 'recruitment.application_withdrawn_by_company';

    /** Sent to the interviewers when a withdrawal cancelled their interview; carries the `interview.ics` cancel. */
    public const string INTERVIEW_CANCELLED_TEAM = 'recruitment.interview_cancelled_team';

    public const string OFFER_SENT = 'recruitment.offer_sent';

    public const string OFFER_REVISED = 'recruitment.offer_revised';

    public const string OFFER_WITHDRAWN = 'recruitment.offer_withdrawn';

    public const string OFFER_EXPIRING = 'recruitment.offer_expiring';

    /** The template for a change to an offer, or null when the job seeker is not emailed about it. */
    public static function keyForOffer(OfferChange $change): ?string
    {
        return match ($change) {
            OfferChange::Sent => self::OFFER_SENT,
            OfferChange::Revised => self::OFFER_REVISED,
            OfferChange::Withdrawn => self::OFFER_WITHDRAWN,
            OfferChange::Expiring => self::OFFER_EXPIRING,
            OfferChange::Accepted, OfferChange::Declined => null,
        };
    }

    /** The template for a change to an interview, or null when the job seeker is not emailed about it. */
    public static function keyForInterview(InterviewChange $change): ?string
    {
        return match ($change) {
            InterviewChange::Scheduled => self::INTERVIEW_SCHEDULED,
            InterviewChange::Rescheduled => self::INTERVIEW_RESCHEDULED,
            InterviewChange::Cancelled => self::INTERVIEW_CANCELLED,
            InterviewChange::Reminder => self::INTERVIEW_REMINDER,
            InterviewChange::Updated => self::INTERVIEW_UPDATED,
            InterviewChange::Declined => self::INTERVIEW_DECLINED,
            InterviewChange::ProposalAccepted, InterviewChange::Confirmed, InterviewChange::Proposed, InterviewChange::RecordingWithdrawn => null,
        };
    }

    /** The template of the outcome, or null for a stage type the applicant is not emailed about. */
    public static function keyFor(StageType $type): ?string
    {
        return match ($type) {
            StageType::Interview => self::INTERVIEW,
            StageType::Offer => self::OFFER,
            StageType::Hired => self::HIRED,
            StageType::Rejected => self::REJECTED,
            StageType::Applied, StageType::Screening, StageType::Assessment, StageType::Withdrawn => null,
        };
    }

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [...self::receivedDefinitions(), ...self::stageDefinitions(), ...self::interviewDefinitions(), ...self::offerDefinitions(), ...self::withdrawalDefinitions()];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function receivedDefinitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::RECEIVED,
                'Sent to an applicant right after they apply, to confirm the company received the application.',
                [
                    new EmailVariable('name', 'The applicant\'s name', 'Siti'),
                    new EmailVariable('job_title', 'The title of the job applied for', 'Backend Developer'),
                    new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('date', 'The date of the application (Jakarta time)', '2026-10-17'),
                    new EmailVariable('message', 'The company\'s own message (its default text for received applications), on its own lines with its label; empty when it has none', "\n\nPesan dari perusahaan: Kami akan menghubungi Anda dalam 7 hari."),
                    new EmailVariable('link', 'The link to the application page, on its own line with its label; empty when the page is not configured', "\n\nLihat lamaran Anda: https://example.test/applications/1"),
                ],
                TranslatedText::fromArray(['id' => 'Lamaran Anda untuk {{job_title}} sudah diterima', 'en' => 'We received your application for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nLamaran Anda untuk posisi {{job_title}} di {{company_name}} sudah kami terima pada {{date}}.{{message}}{{link}}\n\nBila ada perkembangan, kami akan mengabari Anda lewat akun Anda.",
                    'en' => "Hello {{name}},\n\nWe received your application for {{job_title}} at {{company_name}} on {{date}}.{{message}}{{link}}\n\nIf anything changes, we will tell you through your account.",
                ]),
            ),
        ];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function stageDefinitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The applicant\'s name', 'Siti'),
            new EmailVariable('job_title', 'The title of the job applied for', 'Backend Developer'),
            new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
        ];

        return [
            new EmailTemplateDefinition(
                self::INTERVIEW,
                'Sent to an applicant when their application moves to the interview stage.',
                $variables,
                TranslatedText::fromArray(['id' => 'Lamaran Anda untuk {{job_title}} maju ke tahap wawancara', 'en' => 'Your application for {{job_title}} moved to the interview stage']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nKabar baik: {{company_name}} memindahkan lamaran Anda untuk posisi {{job_title}} ke tahap wawancara.\n\nBuka akun Anda untuk melihat statusnya. Semoga sukses!",
                    'en' => "Hello {{name}},\n\nGood news: {{company_name}} moved your application for {{job_title}} to the interview stage.\n\nOpen your account to see its status. Good luck!",
                ]),
            ),
            new EmailTemplateDefinition(
                self::OFFER,
                'Sent to an applicant when their application moves to the offer stage.',
                $variables,
                TranslatedText::fromArray(['id' => 'Lamaran Anda untuk {{job_title}} maju ke tahap penawaran', 'en' => 'Your application for {{job_title}} moved to the offer stage']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} memindahkan lamaran Anda untuk posisi {{job_title}} ke tahap penawaran.\n\nBuka akun Anda untuk melihat statusnya.",
                    'en' => "Hello {{name}},\n\n{{company_name}} moved your application for {{job_title}} to the offer stage.\n\nOpen your account to see its status.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::HIRED,
                'Sent to an applicant when their application is marked as hired.',
                $variables,
                TranslatedText::fromArray(['id' => 'Selamat! Anda diterima untuk {{job_title}}', 'en' => 'Congratulations! You were hired for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nSelamat! {{company_name}} menandai lamaran Anda untuk posisi {{job_title}} sebagai diterima.\n\nBuka akun Anda untuk melihat statusnya.",
                    'en' => "Hello {{name}},\n\nCongratulations! {{company_name}} marked your application for {{job_title}} as hired.\n\nOpen your account to see its status.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::REJECTED,
                'Sent to an applicant when their application is rejected. It never states the reason.',
                $variables,
                TranslatedText::fromArray(['id' => 'Pembaruan lamaran Anda untuk {{job_title}}', 'en' => 'An update on your application for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nTerima kasih telah melamar posisi {{job_title}} di {{company_name}}. Setelah ditinjau, mereka tidak melanjutkan lamaran Anda kali ini.\n\nKami berharap Anda segera mendapat peluang yang tepat.",
                    'en' => "Hello {{name}},\n\nThank you for applying for {{job_title}} at {{company_name}}. After review, they will not move forward with your application this time.\n\nWe wish you every success in finding the right opportunity.",
                ]),
            ),
        ];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function interviewDefinitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The applicant\'s name', 'Siti'),
            new EmailVariable('job_title', 'The title of the job applied for', 'Backend Developer'),
            new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
            new EmailVariable('starts_at', 'When the interview starts (Jakarta time)', '2026-10-12 09:00 WIB'),
            new EmailVariable('format', 'Online, Onsite or Video in the platform', 'Online'),
            new EmailVariable('place', 'The meeting link, the place of the interview, or where to join a video interview', 'https://meet.example.com/abc'),
            new EmailVariable('recording', 'Whether the interview is recorded (only with the applicant\'s consent)', 'No'),
            new EmailVariable('note', 'The recruiter\'s note for the applicant, on its own lines with its label; empty when there is none', "\n\nNote: Please bring your ID card."),
        ];

        return [
            new EmailTemplateDefinition(
                self::INTERVIEW_SCHEDULED,
                'Sent to an applicant when an interview is scheduled for their application.',
                $variables,
                TranslatedText::fromArray(['id' => 'Jadwal wawancara untuk {{job_title}}', 'en' => 'Interview scheduled for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} menjadwalkan wawancara untuk posisi {{job_title}}.\n\nWaktu: {{starts_at}}\nFormat: {{format}}\nTempat atau tautan: {{place}}\nDirekam: {{recording}}{{note}}\n\nBuka akun Anda untuk mengonfirmasi atau mengusulkan waktu lain.",
                    'en' => "Hello {{name}},\n\n{{company_name}} scheduled an interview for {{job_title}}.\n\nTime: {{starts_at}}\nFormat: {{format}}\nPlace or link: {{place}}\nRecorded: {{recording}}{{note}}\n\nOpen your account to confirm it or propose another time.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::INTERVIEW_RESCHEDULED,
                'Sent to an applicant when the time of their interview changed.',
                $variables,
                TranslatedText::fromArray(['id' => 'Jadwal wawancara untuk {{job_title}} berubah', 'en' => 'Your interview for {{job_title}} was rescheduled']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} mengubah jadwal wawancara untuk posisi {{job_title}}.\n\nWaktu baru: {{starts_at}}\nFormat: {{format}}\nTempat atau tautan: {{place}}\nDirekam: {{recording}}{{note}}\n\nBuka akun Anda untuk mengonfirmasi atau mengusulkan waktu lain.",
                    'en' => "Hello {{name}},\n\n{{company_name}} changed the interview for {{job_title}}.\n\nNew time: {{starts_at}}\nFormat: {{format}}\nPlace or link: {{place}}\nRecorded: {{recording}}{{note}}\n\nOpen your account to confirm it or propose another time.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::INTERVIEW_CANCELLED,
                'Sent to an applicant when their interview is cancelled.',
                $variables,
                TranslatedText::fromArray(['id' => 'Wawancara untuk {{job_title}} dibatalkan', 'en' => 'Your interview for {{job_title}} was cancelled']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} membatalkan wawancara untuk posisi {{job_title}} yang dijadwalkan pada {{starts_at}}.\n\nBuka akun Anda untuk melihat statusnya.",
                    'en' => "Hello {{name}},\n\n{{company_name}} cancelled the interview for {{job_title}} that was set for {{starts_at}}.\n\nOpen your account to see its status.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::INTERVIEW_UPDATED,
                'Sent to an applicant when the details of their interview (the note) changed and the time stayed.',
                $variables,
                TranslatedText::fromArray(['id' => 'Detail wawancara untuk {{job_title}} diperbarui', 'en' => 'The details of your interview for {{job_title}} were updated']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} memperbarui detail wawancara untuk posisi {{job_title}}. Waktunya tidak berubah.\n\nWaktu: {{starts_at}}\nFormat: {{format}}\nTempat atau tautan: {{place}}\nDirekam: {{recording}}{{note}}\n\nBuka akun Anda untuk melihat jadwalnya.",
                    'en' => "Hello {{name}},\n\n{{company_name}} updated the details of the interview for {{job_title}}. The time did not change.\n\nTime: {{starts_at}}\nFormat: {{format}}\nPlace or link: {{place}}\nRecorded: {{recording}}{{note}}\n\nOpen your account to see the schedule.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::INTERVIEW_DECLINED,
                'Sent to an applicant who declined their interview, so the calendar event is removed.',
                $variables,
                TranslatedText::fromArray(['id' => 'Anda menolak wawancara untuk {{job_title}}', 'en' => 'You declined the interview for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nAnda menolak wawancara untuk posisi {{job_title}} di {{company_name}} yang dijadwalkan pada {{starts_at}}. Acara tersebut dihapus dari kalender Anda.",
                    'en' => "Hello {{name}},\n\nYou declined the interview for {{job_title}} at {{company_name}} that was set for {{starts_at}}. The event is removed from your calendar.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::INTERVIEW_REMINDER,
                'Sent to an applicant about a day before a confirmed interview.',
                $variables,
                TranslatedText::fromArray(['id' => 'Pengingat: wawancara untuk {{job_title}}', 'en' => 'Reminder: your interview for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nIni pengingat wawancara Anda untuk posisi {{job_title}} di {{company_name}}.\n\nWaktu: {{starts_at}}\nFormat: {{format}}\nTempat atau tautan: {{place}}\nDirekam: {{recording}}{{note}}\n\nSemoga sukses!",
                    'en' => "Hello {{name}},\n\nA reminder of your interview for {{job_title}} at {{company_name}}.\n\nTime: {{starts_at}}\nFormat: {{format}}\nPlace or link: {{place}}\nRecorded: {{recording}}{{note}}\n\nGood luck!",
                ]),
            ),
        ];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function offerDefinitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The applicant\'s name', 'Siti'),
            new EmailVariable('job_title', 'The title of the job applied for', 'Backend Developer'),
            new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
            new EmailVariable('position', 'The position that is offered', 'Backend Developer'),
            new EmailVariable('salary', 'The monthly salary offered', 'Rp 12.000.000'),
            new EmailVariable('start_date', 'The start date', '2026-11-02'),
            new EmailVariable('expires_at', 'The deadline to answer (Jakarta time)', '2026-10-20 17:00 WIB'),
            new EmailVariable('note', 'The company\'s note on the offer, on its own lines with its label; empty when there is none', "\n\nCatatan: Harap konfirmasi sebelum batas waktu."),
        ];

        return [
            new EmailTemplateDefinition(
                self::OFFER_SENT,
                'Sent to an applicant when a job offer is sent for their application.',
                $variables,
                TranslatedText::fromArray(['id' => 'Penawaran kerja untuk {{job_title}}', 'en' => 'Job offer for {{job_title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} mengirim penawaran kerja untuk {{job_title}}.\n\nPosisi: {{position}}\nGaji per bulan: {{salary}}\nTanggal mulai: {{start_date}}\nBerlaku sampai: {{expires_at}}{{note}}\n\nBuka akun Anda untuk menerima atau menolak penawaran ini.",
                    'en' => "Hello {{name}},\n\n{{company_name}} sent you a job offer for {{job_title}}.\n\nPosition: {{position}}\nMonthly salary: {{salary}}\nStart date: {{start_date}}\nValid until: {{expires_at}}{{note}}\n\nOpen your account to accept or decline the offer.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::OFFER_REVISED,
                'Sent to an applicant when their job offer was replaced by a new one.',
                $variables,
                TranslatedText::fromArray(['id' => 'Penawaran kerja untuk {{job_title}} diperbarui', 'en' => 'Your job offer for {{job_title}} was updated']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} memperbarui penawaran kerja untuk {{job_title}}. Penawaran sebelumnya tidak berlaku lagi.\n\nPosisi: {{position}}\nGaji per bulan: {{salary}}\nTanggal mulai: {{start_date}}\nBerlaku sampai: {{expires_at}}{{note}}\n\nBuka akun Anda untuk menerima atau menolak penawaran ini.",
                    'en' => "Hello {{name}},\n\n{{company_name}} updated the job offer for {{job_title}}. The earlier offer no longer applies.\n\nPosition: {{position}}\nMonthly salary: {{salary}}\nStart date: {{start_date}}\nValid until: {{expires_at}}{{note}}\n\nOpen your account to accept or decline the offer.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::OFFER_WITHDRAWN,
                'Sent to an applicant when their job offer is withdrawn.',
                $variables,
                TranslatedText::fromArray(['id' => 'Penawaran kerja untuk {{job_title}} ditarik', 'en' => 'Your job offer for {{job_title}} was withdrawn']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} menarik penawaran kerja untuk {{job_title}}.\n\nBuka akun Anda untuk melihat status lamaran.",
                    'en' => "Hello {{name}},\n\n{{company_name}} withdrew the job offer for {{job_title}}.\n\nOpen your account to see the status of your application.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::OFFER_EXPIRING,
                'Sent to an applicant about a day before their job offer expires.',
                $variables,
                TranslatedText::fromArray(['id' => 'Penawaran kerja untuk {{job_title}} segera berakhir', 'en' => 'Your job offer for {{job_title}} expires soon']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nPenawaran kerja dari {{company_name}} untuk {{job_title}} berlaku sampai {{expires_at}}.\n\nPosisi: {{position}}\nGaji per bulan: {{salary}}\nTanggal mulai: {{start_date}}\n\nBuka akun Anda untuk menerima atau menolaknya sebelum waktunya habis.",
                    'en' => "Hello {{name}},\n\nThe job offer from {{company_name}} for {{job_title}} is valid until {{expires_at}}.\n\nPosition: {{position}}\nMonthly salary: {{salary}}\nStart date: {{start_date}}\n\nOpen your account to accept or decline it before time runs out.",
                ]),
            ),
        ];
    }

    /** @return list<EmailTemplateDefinition> */
    private static function withdrawalDefinitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::APPLICATION_WITHDRAWN_BY_COMPANY,
                'Sent to an applicant when the company marks their application as withdrawn (they backed out outside the platform). The calendar file cancels an interview that was scheduled.',
                [
                    new EmailVariable('name', 'The applicant\'s name', 'Siti'),
                    new EmailVariable('job_title', 'The title of the job applied for', 'Backend Developer'),
                    new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('reason', 'The reason the company recorded, on its own lines with its label', "\n\nAlasan: Gaji"),
                ],
                TranslatedText::fromArray(['id' => 'Lamaran Anda untuk {{job_title}} dicatat sebagai mengundurkan diri', 'en' => 'Your application for {{job_title}} was recorded as withdrawn']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{company_name}} mencatat bahwa Anda mengundurkan diri dari lamaran untuk posisi {{job_title}}.{{reason}}\n\nJika ini tidak benar, hubungi {{company_name}}.",
                    'en' => "Hello {{name}},\n\n{{company_name}} recorded that you withdrew your application for {{job_title}}.{{reason}}\n\nIf this is not right, contact {{company_name}}.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::INTERVIEW_CANCELLED_TEAM,
                'Sent to the interviewer and the panel when a withdrawn application cancels their interview. The calendar file removes the event they added.',
                [
                    new EmailVariable('name', 'The team member\'s name', 'Budi'),
                    new EmailVariable('job_title', 'The title of the job', 'Backend Developer'),
                    new EmailVariable('company_name', 'The name of the company', 'PT Contoh Maju'),
                    new EmailVariable('candidate', 'The candidate\'s name', 'Siti Rahma'),
                    new EmailVariable('starts_at', 'The start of the interview (Jakarta time)', '2026-10-20 10:00 WIB'),
                    new EmailVariable('link', 'The link to the application page, on its own line with its label; empty when the page is not configured', "\n\nBuka lamaran: https://example.test/applications/1"),
                ],
                TranslatedText::fromArray(['id' => 'Wawancara {{candidate}} untuk {{job_title}} dibatalkan', 'en' => 'The interview with {{candidate}} for {{job_title}} was cancelled']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nWawancara dengan {{candidate}} untuk posisi {{job_title}} pada {{starts_at}} dibatalkan. Alasannya: lamaran ditarik.{{link}}\n\nFile kalender terlampir menghapus acara ini dari kalender Anda.",
                    'en' => "Hello {{name}},\n\nThe interview with {{candidate}} for {{job_title}} on {{starts_at}} was cancelled. Reason: the application was withdrawn.{{link}}\n\nThe attached calendar file removes this event from your calendar.",
                ]),
            ),
        ];
    }
}
