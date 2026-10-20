<?php

namespace Src\Identity\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Identity context, registered in the shared catalog by its service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class IdentityEmailTemplates
{
    public const string EMAIL_VERIFICATION = 'identity.email_verification';

    public const string PASSWORD_RESET = 'identity.password_reset';

    public const string TWO_FACTOR_REQUIRED = 'identity.two_factor_required';

    public const string TENANT_INTERNAL = 'identity.tenant_internal';

    public const string ACTIVATION_JOB_SEEKER = 'activation.job_seeker';

    public const string ACTIVATION_JOB_SEEKER_NO_PASSWORD = 'activation.job_seeker_no_password';

    public const string ACTIVATION_COMPANY = 'activation.company';

    public const string ACTIVATION_REMINDER = 'activation.reminder';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The recipient\'s name', 'Siti'),
            new EmailVariable('code', 'The six-digit code', '049213', required: true),
            new EmailVariable('minutes', 'How many minutes the code stays valid', '15'),
        ];

        return [
            new EmailTemplateDefinition(
                self::EMAIL_VERIFICATION,
                'Sent to a new account with the code that verifies its email address.',
                $variables,
                TranslatedText::fromArray(['id' => 'Kode verifikasi Anda', 'en' => 'Your verification code']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nGunakan kode ini untuk memverifikasi alamat email Anda:\n\n# {{code}}\n\nKode berlaku selama {{minutes}} menit dan hanya dapat digunakan sekali.\n\nJika Anda tidak membuat akun, abaikan email ini.",
                    'en' => "Hello {{name}},\n\nUse this code to verify your email address:\n\n# {{code}}\n\nThe code is valid for {{minutes}} minutes and can be used once.\n\nIf you did not create an account, you can ignore this email.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::PASSWORD_RESET,
                'Sent when someone asks to reset a password, with the code that allows it.',
                $variables,
                TranslatedText::fromArray(['id' => 'Kode reset kata sandi Anda', 'en' => 'Your password reset code']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nGunakan kode ini untuk menetapkan kata sandi baru:\n\n# {{code}}\n\nKode berlaku selama {{minutes}} menit dan hanya dapat digunakan sekali.\n\nJika Anda tidak meminta reset kata sandi, abaikan email ini. Kata sandi Anda tidak berubah.",
                    'en' => "Hello {{name}},\n\nUse this code to set a new password:\n\n# {{code}}\n\nThe code is valid for {{minutes}} minutes and can be used once.\n\nIf you did not ask to reset your password, you can ignore this email. Your password stays unchanged.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::TWO_FACTOR_REQUIRED,
                'Sent to a company member when two-factor sign-in became required for their account (they were promoted to company admin, or their company switched it on) and they have not set it up yet.',
                [new EmailVariable('name', 'The recipient\'s name', 'Budi')],
                TranslatedText::fromArray(['id' => 'Verifikasi dua langkah kini diwajibkan', 'en' => 'Two-step verification is now required']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nUntuk menjaga keamanan data pelamar, akun Anda kini wajib memakai verifikasi dua langkah. Sesi Anda yang sedang aktif telah diakhiri.\n\nSaat Anda masuk berikutnya, Anda akan diminta memasang aplikasi autentikator (misalnya Google Authenticator) di ponsel Anda. Siapkan ponsel Anda sebelum masuk.",
                    'en' => "Hello {{name}},\n\nTo keep applicant data safe, your account now needs two-step verification. Your active sessions have ended.\n\nThe next time you sign in you will be asked to set up an authenticator app (for example Google Authenticator) on your phone. Have your phone ready before you sign in.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::TENANT_INTERNAL,
                'Sent to a company member when their company became an internal tenant of an ERP group: their sessions ended and the next sign-in follows the internal rules.',
                [new EmailVariable('name', 'The recipient\'s name', 'Budi')],
                TranslatedText::fromArray(['id' => 'Aturan masuk akun Anda berubah', 'en' => 'The sign-in rules of your account changed']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nPerusahaan Anda kini terhubung ke grup perusahaan Anda di ONESIS. Sesi Anda yang sedang aktif telah diakhiri.\n\nMulai sekarang sesi masuk berlaku 12 jam, dan grup Anda dapat mewajibkan verifikasi dua langkah. Saat Anda masuk berikutnya, Anda mungkin diminta memasang aplikasi autentikator di ponsel Anda.",
                    'en' => "Hello {{name}},\n\nYour company is now linked to your company group on ONESIS. Your active sessions have ended.\n\nFrom now on a sign-in lasts 12 hours, and your group may require two-step verification. The next time you sign in you may be asked to set up an authenticator app on your phone.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::ACTIVATION_JOB_SEEKER,
                'Sent once to a job seeker imported from karir.pkp whose old password still works: how to sign in to the new site. Never holds a code or a password.',
                self::activationVariables(),
                TranslatedText::fromArray(['id' => 'Akun karir.pkp Anda kini ada di ONESIS Career', 'en' => 'Your karir.pkp account is now on ONESIS Career']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nSeperti kami kabarkan, karir.pkp sudah pindah ke **ONESIS Career**. Akun Anda dengan email ini sudah dipindahkan beserta profil, lamaran, lowongan tersimpan, dan pilihan job alert Anda.\n\n**Masuk:** buka {{sign_in_url}} dan masuk dengan email ini dan kata sandi lama Anda. Jika kata sandi lama tidak berhasil, atur kata sandi baru di {{activation_url}}.\n\nSaat pertama masuk, Anda akan menyetujui ketentuan penggunaan yang baru dan melihat ringkasan apa yang sudah dan belum dipindahkan. Profil Anda **tidak terlihat** oleh perusahaan sampai Anda sendiri mengaktifkannya di pengaturan.\n\nSampai Anda masuk, kami tidak mengirim email rekomendasi atau pengingat lain; Anda hanya akan menerima satu pengingat jika belum masuk dalam {{reminder_days}} hari.\n\nKami tidak pernah meminta kata sandi atau kode Anda lewat email, WhatsApp, atau telepon. Tidak merasa mendaftar di karir.pkp? Abaikan email ini. Bantuan: {{support_email}}.\n\nTim ONESIS Career",
                    'en' => "Hello {{name}},\n\nAs we told you, karir.pkp has moved to **ONESIS Career**. Your account with this email has been moved, with your profile, applications, saved jobs and job alert choices.\n\n**Sign in:** open {{sign_in_url}} and sign in with this email and your old password. If the old password does not work, set a new one at {{activation_url}}.\n\nAt your first sign-in you will accept the new terms of use and see a summary of what was and was not moved. Your profile is **not visible** to companies until you turn that on yourself in the settings.\n\nUntil you sign in we send no recommendation emails or other reminders; you will get a single reminder if you have not signed in within {{reminder_days}} days.\n\nWe never ask for your password or a code by email, WhatsApp or phone. Did not sign up at karir.pkp? Ignore this email. Help: {{support_email}}.\n\nThe ONESIS Career team",
                ]),
            ),
            new EmailTemplateDefinition(
                self::ACTIVATION_JOB_SEEKER_NO_PASSWORD,
                'Sent once to a job seeker imported from karir.pkp whose old password could not be carried over: how to set a new one. Never holds a code or a password.',
                self::activationVariables(),
                TranslatedText::fromArray(['id' => 'Aktifkan akun karir.pkp Anda di ONESIS Career', 'en' => 'Activate your karir.pkp account on ONESIS Career']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nkarir.pkp sudah pindah ke **ONESIS Career**. Akun Anda dengan email ini sudah dipindahkan beserta profil, lamaran, lowongan tersimpan, dan pilihan job alert Anda. Karena alasan teknis kata sandi lama tidak dapat dibawa, Anda perlu **mengatur kata sandi baru** satu kali.\n\n**Aktifkan akun:** buka {{activation_url}}, masukkan email ini, lalu pilih \"Atur kata sandi baru\". Kami akan mengirim kode 6 digit ke email ini pada saat itu (berlaku 15 menit), jadi Anda dapat membuka tautan kapan saja.\n\nSaat pertama masuk, Anda akan menyetujui ketentuan penggunaan yang baru dan melihat ringkasan apa yang sudah dan belum dipindahkan. Profil Anda **tidak terlihat** oleh perusahaan sampai Anda sendiri mengaktifkannya.\n\nKami tidak pernah meminta kata sandi atau kode Anda lewat email, WhatsApp, atau telepon. Tidak merasa mendaftar di karir.pkp? Abaikan email ini. Bantuan: {{support_email}}.\n\nTim ONESIS Career",
                    'en' => "Hello {{name}},\n\nkarir.pkp has moved to **ONESIS Career**. Your account with this email has been moved, with your profile, applications, saved jobs and job alert choices. For a technical reason your old password could not be carried over, so you need to **set a new password** once.\n\n**Activate your account:** open {{activation_url}}, enter this email, then choose \"Set a new password\". We will then send a 6-digit code to this email (valid for 15 minutes), so you can open the link at any time.\n\nAt your first sign-in you will accept the new terms of use and see a summary of what was and was not moved. Your profile is **not visible** to companies until you turn that on yourself.\n\nWe never ask for your password or a code by email, WhatsApp or phone. Did not sign up at karir.pkp? Ignore this email. Help: {{support_email}}.\n\nThe ONESIS Career team",
                ]),
            ),
            new EmailTemplateDefinition(
                self::ACTIVATION_COMPANY,
                'Sent once to a company owner imported from karir.pkp, after a personal call: how to get in. Never holds a code or a password.',
                self::activationVariables(),
                TranslatedText::fromArray(['id' => 'Akun {{company_name}} sudah ada di ONESIS Career', 'en' => 'The account of {{company_name}} is now on ONESIS Career']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nAkun karir.pkp **{{company_name}}** sudah dipindahkan ke **ONESIS Career**: profil perusahaan, lowongan beserta pelamarnya, dan kandidat tersimpan.\n\n**Yang perlu Anda siapkan:** aplikasi authenticator di ponsel (untuk verifikasi dua langkah, wajib bagi admin perusahaan) dan email ini.\n\n**Langkah:** buka {{activation_url}}, atur kata sandi baru atau masuk dengan kata sandi lama bila masih berlaku, daftarkan authenticator, lalu setujui ketentuan penggunaan. Anda akan melihat ringkasan pemindahan: lowongan yang menjadi draf karena data belum lengkap, lowongan yang ditutup karena sebelumnya melamar lewat email atau tautan luar, dan hal yang tidak ikut dipindahkan (anggota tim, riwayat chat).\n\nKami menghubungi Anda lebih dulu agar lowongan Anda siap sebelum pencari kerja kembali. Kami tidak pernah meminta kata sandi atau kode lewat email, WhatsApp, atau telepon. Bantuan langsung: {{support_contact}}.\n\nTim ONESIS Career",
                    'en' => "Hello {{name}},\n\nThe karir.pkp account of **{{company_name}}** has been moved to **ONESIS Career**: the company profile, the jobs with their applicants, and saved candidates.\n\n**What you need:** an authenticator app on your phone (two-step verification, required for company admins) and this email.\n\n**Steps:** open {{activation_url}}, set a new password or sign in with the old one if it still works, register the authenticator, then accept the terms of use. You will see a summary of the move: jobs that became drafts because data was incomplete, jobs that were closed because they used to take applications by email or an outside link, and what was not moved (team members, chat history).\n\nWe contact you first so that your jobs are ready before job seekers come back. We never ask for a password or a code by email, WhatsApp or phone. Direct help: {{support_contact}}.\n\nThe ONESIS Career team",
                ]),
            ),
            new EmailTemplateDefinition(
                self::ACTIVATION_REMINDER,
                'Sent once, some days after the activation email, to an imported account that has not signed in yet. Nothing else is sent until it does.',
                self::activationVariables(),
                TranslatedText::fromArray(['id' => 'Akun karir.pkp Anda masih menunggu di ONESIS Career', 'en' => 'Your karir.pkp account is still waiting on ONESIS Career']),
                TranslatedText::fromArray([
                    'id' => 'Halo {{name}}, akun Anda di ONESIS Career sudah siap, tetapi Anda belum masuk. Masuk atau aktifkan akun di {{activation_url}}. Ini pengingat terakhir; kami tidak mengirim email lain sampai Anda masuk. Kami tidak pernah meminta kata sandi atau kode lewat email. Bantuan: {{support_email}}.',
                    'en' => 'Hello {{name}}, your account on ONESIS Career is ready, but you have not signed in yet. Sign in or activate your account at {{activation_url}}. This is the last reminder; we send nothing else until you sign in. We never ask for a password or a code by email. Help: {{support_email}}.',
                ]),
            ),
        ];
    }

    /** @return list<EmailVariable> */
    private static function activationVariables(): array
    {
        return [
            new EmailVariable('name', 'The recipient\'s name', 'Siti'),
            new EmailVariable('company_name', 'The name of the company (company owners only)', 'PT Maju Jaya'),
            new EmailVariable('sign_in_url', 'The sign-in page of the job seeker site', 'https://karir.example/masuk'),
            new EmailVariable('activation_url', 'The page that explains the activation and starts it', 'https://karir.example/aktifkan-akun', required: true),
            new EmailVariable('reminder_days', 'After how many days the single reminder comes', '7'),
            new EmailVariable('support_email', 'The support address (setting platform.support_email)', 'bantuan@example.test'),
            new EmailVariable('support_contact', 'The support address and, when set, the WhatsApp number', 'bantuan@example.test / WhatsApp 0812-0000-0000'),
        ];
    }
}
