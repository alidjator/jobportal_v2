<?php

namespace Src\DemoData\Application;

use Src\Company\Domain\MemberPreset;
use Src\Recruitment\Domain\ReviewDecision;
use Src\Recruitment\Domain\Rsvp;

/**
 * What the members of the verified company find in their own lists: the interviews they were asked to hold or join (and how they answered), the shortlists the recruiter asked the hiring manager to review (and what
 * the hiring manager answered), and the long-text answers of the candidates of the UI/UX test. Applications are named `"{job key}:{seeker}"` as `DemoApplications` returns them. The texts are Indonesian, as the team reads them.
 */
final class DemoMyWorkContent
{
    /**
     * The extra panel members of an interview, by application and member preset.
     *
     * @return array<string, list<string>>
     */
    public static function panels(): array
    {
        return [
            'backend:2' => [MemberPreset::HiringManager->value],
            'data:1' => [MemberPreset::Recruiter->value],
            'data:5' => [MemberPreset::Interviewer->value],
        ];
    }

    /**
     * The answers to the invitations of the interviews: the application, the member preset that answers, the answer and its note. An interview that is not listed waits for its answer.
     *
     * @return list<array{string, string, Rsvp, ?string}>
     */
    public static function rsvps(): array
    {
        return [
            ['backend:2', MemberPreset::Interviewer->value, Rsvp::Accepted, null],
            ['backend:2', MemberPreset::HiringManager->value, Rsvp::Accepted, 'Saya ikut sebagai panel untuk sesi teknis.'],
            ['backend:6', MemberPreset::Interviewer->value, Rsvp::Declined, 'Bentrok dengan sprint review tim. Mohon dijadwalkan ulang atau dialihkan ke Pak Hendra.'],
            ['backend:7', MemberPreset::Interviewer->value, Rsvp::Accepted, 'Siap. Mohon kirimkan CV terbarunya sehari sebelumnya.'],
            ['data:5', MemberPreset::HiringManager->value, Rsvp::Accepted, null],
            ['data:1', MemberPreset::Recruiter->value, Rsvp::Accepted, null],
        ];
    }

    /**
     * The shortlists the recruiter asks the hiring manager to review: the job, the applications, the message, the days until the due date and the answers given so far (application, decision, reason). The answers that are
     * not listed are still awaited.
     *
     * @return list<array{job: string, applications: list<string>, message: string, days: int, answers: list<array{string, ReviewDecision, ?string}>}>
     */
    public static function reviewRequests(): array
    {
        return [
            [
                'job' => 'backend',
                'applications' => ['backend:1', 'backend:0'],
                'message' => 'Mohon lihat dua kandidat backend ini sebelum shortlist hari Jumat.',
                'days' => 5,
                'answers' => [['backend:1', ReviewDecision::Advance, null]],
            ],
            [
                'job' => 'data',
                'applications' => ['data:7', 'data:3', 'data:4'],
                'message' => 'Shortlist analis data: pilih yang layak lanjut ke wawancara.',
                'days' => 3,
                'answers' => [
                    ['data:7', ReviewDecision::Advance, null],
                    ['data:3', ReviewDecision::Maybe, 'Pengalaman SQL cukup, tetapi belum ada contoh visualisasi data.'],
                ],
            ],
        ];
    }

    /**
     * The case study of the UI/UX test, answered by a candidate: `$strong` answers are complete, the others short. Two answers, one for each question.
     *
     * @return list<string>
     */
    public static function caseAnswers(bool $strong): array
    {
        return $strong
            ? [
                'Bagian yang paling membingungkan menurut saya adalah formulir lamaran yang meminta data yang sama dua kali: sekali di profil dan sekali di tiap lamaran. Pengguna tidak tahu data mana yang dipakai. Perbaikannya: isi otomatis dari profil, tampilkan ringkasan yang bisa diubah, dan beri tanda jelas untuk isian yang wajib. Saya akan mengujinya dengan lima pengguna dan memantau berapa banyak yang berhenti di tengah formulir.',
                'Saya mulai dari alur sekarang: hitung langkah dan isian, lalu buang yang tidak perlu untuk keputusan pertama. Pendaftaran cukup email dan kata sandi; data lain menyusul setelah masuk. Keamanan dijaga dengan verifikasi email, pengukur kekuatan kata sandi yang membantu, dan opsi masuk dengan Google. Hasilnya diuji dengan tolok ukur tingkat penyelesaian pendaftaran.',
            ]
            : [
                'Menurut saya halaman filter lowongan terlalu ramai. Saya akan menyederhanakan tampilannya dan memakai warna yang lebih tenang.',
                'Pendaftaran dibuat satu halaman saja dengan tombol yang jelas. Kata sandi tetap harus kuat agar akun aman.',
            ];
    }
}
