<?php

namespace Src\JobSeeker\Application\Export;

/** The `README.txt` of the archive, in Indonesian (the language of the people it is written for): what the file holds and what it leaves out. */
final class DataExportReadme
{
    /** What each part of `data.json` holds, by its key; a key that is not listed here is still exported and described by its name only. */
    private const array PARTS = [
        'account' => 'Data akun: email, nama, tipe akun, tanggal pendaftaran dan login terakhir, persetujuan syarat dan ketentuan beserta versinya.',
        'profile' => 'Profil: data diri, pengalaman kerja, pendidikan, keahlian, bahasa, tautan, pengaturan visibilitas, nama resume, dan perusahaan yang pernah membuka profil Anda.',
        'applications' => 'Lamaran: lowongan dan perusahaan, riwayat tahapan sebagaimana terlihat oleh Anda, alasan penolakan (kode), catatan penarikan, jawaban pertanyaan seleksi, salinan profil saat melamar, wawancara, penawaran kerja, dan hasil asesmen yang boleh Anda baca.',
        'assessments' => 'Asesmen: undangan tes, penjelasan hasil yang boleh Anda baca, dan gambar yang Anda unggah saat tes.',
        'job_invitations' => 'Undangan melamar yang Anda terima dari perusahaan.',
        'saved_jobs' => 'Lowongan yang Anda simpan.',
        'job_alerts' => 'Alert lowongan yang Anda buat.',
        'conversations' => 'Percakapan Anda dengan perusahaan, beserta pesan dari kedua pihak dan lampiran Anda.',
        'reviews' => 'Ulasan perusahaan yang Anda tulis.',
        'notification_preferences' => 'Pengaturan notifikasi Anda.',
        'talent_pool' => 'Persetujuan Anda untuk disimpan di talent pool perusahaan.',
    ];

    /** @param  list<string>  $keys  the keys of the parts that were exported */
    public static function text(array $keys, string $generatedAt): string
    {
        $lines = [
            'SALINAN DATA PRIBADI ANDA',
            '',
            "Dibuat pada: {$generatedAt}",
            '',
            'Berkas ini berisi salinan data pribadi Anda yang kami simpan, sesuai hak Anda menurut UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.',
            'Berkas ini akan dihapus dari server kami 24 jam setelah dibuat. Simpan di tempat yang aman: isinya data pribadi.',
            '',
            'ISI',
            '- data.json: seluruh data dalam format JSON, satu bagian per kategori (lihat di bawah).',
            '- files/: berkas Anda (resume, CV yang dibuat sistem, foto profil, lampiran chat, gambar tes).',
            '- README.txt: berkas ini.',
            '',
            'BAGIAN DI data.json',
        ];

        foreach ($keys as $key) {
            $lines[] = "- {$key}: ".(self::PARTS[$key] ?? 'data kategori ini.');
        }

        array_push(
            $lines,
            '',
            'YANG TIDAK DISERTAKAN',
            'Data yang dimiliki perusahaan atau psikolog, bukan data Anda sendiri, tidak disertakan: catatan dan penilaian tim perekrut, scorecard, catatan internal penolakan dan penarikan, catatan talent pool, alasan pembatasan, teks laporan psikologi milik psikolog beserta catatan telaahnya, dan rincian sinyal integritas tes (hanya tingkatnya yang disertakan).',
            'Pertanyaan tentang data Anda: hubungi kami melalui kontak yang tercantum di situs.',
        );

        return implode("\n", $lines)."\n";
    }
}
