# Tasklist Proyek Talent Acquisition

Diperbarui: 20 Oktober 2026.

**Waktu selesai** hanya diisi untuk task yang sudah selesai.

**Type:** FE = Frontend · BE = Backend · API = fitur dan endpoint API

**Status:** Selesai · Sedang dikerjakan · Menunggu pengecekan · Siap dikerjakan · Terhambat · Belum dimulai

**Urutan:** yang sudah selesai lebih dulu (menurut tanggal selesai), lalu task lain menurut urutan pengerjaan.

| No | Type | Task | Status | Waktu selesai | Keterangan |
|---|---|---|---|---|---|
| 1 | BE | Pindah database ke PostgreSQL 16 | Selesai | 10 Okt 2026 | |
| 2 | BE | Skrip import data karir.pkp dan rehearsal pertama | Selesai | 13 Okt 2026 | 964 avatar dan 3 CV tidak ada di salinan file |
| 3 | BE | Fondasi backend: identitas, pengaturan, notifikasi, penyimpanan file, audit log, master data | Selesai | 14 Okt 2026 | Fase 0 |
| 4 | API | Fase 1–7: alur rekrutmen inti, kolaborasi, pencarian dan matching, konten, asesmen, tele-interview | Selesai | 14 Okt 2026 | |
| 5 | FE | Desain front end: tiga aplikasi, peta halaman, kontrak link | Selesai | 19 Okt 2026 | |
| 6 | API | Fase 8 sampai #29: panel admin, hak akses, ERP (grup, SSO, approval, requisition), interview, penawaran, hiring, talent pool, pencarian pelamar, lokasi | Selesai | 19 Okt 2026 | Rilis terakhir v0.9.1 |
| 7 | BE | Perubahan import untuk aktivasi akun (#34), salinan isi lowongan (#35), riwayat import (#36) | Selesai | 20 Okt 2026 | Di branch `phase-8-activation`, belum masuk `master` |
| 8 | API | Aktivasi akun hasil import (#34) | Selesai | 20 Okt 2026 | Belum masuk `master` |
| 9 | API | Salinan isi lowongan di lamaran (#35) dan riwayat import dikeluarkan dari laporan (#36) | Selesai | 20 Okt 2026 | Belum masuk `master` |
| 10 | API | Perbaikan tim perusahaan (#37) | Menunggu pengecekan | | Kode sudah ditulis |
| 11 | API | Kartu antrean admin (#38) | Siap dikerjakan | | |
| 12 | BE | Pengecekan lengkap (`composer check`) untuk #34–#38 | Siap dikerjakan | | Dijalankan user, setelah 10 dan 11 |
| 13 | BE | Gabungkan ke `master`, buat tag rilis, push ke GitHub | Siap dikerjakan | | Push dilakukan user |
| 14 | BE | Jalankan migrasi baru di database dev dan test | Siap dikerjakan | | Dijalankan user |
| 15 | BE | Lengkapi file avatar dan CV lama yang hilang | Siap dikerjakan | | |
| 16 | API | Endpoint yang diminta front end: link `.ics` tim dan daftar `my_scoring` | Siap dikerjakan | | |
| 17 | BE | Rehearsal kedua import (mencakup #34, #35, #36) | Terhambat | | Menunggu 14 dan salinan baru data karir.pkp |
| 18 | BE | Bandingkan hasil rehearsal kedua dengan yang pertama | Terhambat | | Menunggu 17 |
| 19 | BE | Perbaiki tes yang kadang gagal (`TestAuthoringTest`) | Siap dikerjakan | | Lolos kalau diulang |
| 20 | FE | Putuskan poin yang masih terbuka: IP klien di balik proxy Nuxt, layar kompleks, teks | Siap dikerjakan | | Keputusan user |
| 21 | API | Pemantauan antrean / queue watch (#30) | Siap dikerjakan | | Wajib sebelum go-live |
| 22 | FE | Siapkan workspace (tiga aplikasi dan paket bersama) | Terhambat | | Menunggu 17 |
| 23 | BE | Parser webhook bounce dan komplain dari penyedia SMTP | Terhambat | | Menunggu pilihan penyedia SMTP |
| 24 | API | Posting lowongan, termasuk lowongan dengan beberapa lokasi (#20) | Siap dikerjakan | | Besar, beberapa sesi |
| 25 | FE | Paket bersama: klien API, tema UI dan komponen, teks `id`/`en` | Terhambat | | Menunggu 22 |
| 26 | BE | Siapkan server produksi dan PHP | Belum dimulai | | `deployment-checklist.md` bagian 1 |
| 27 | BE | Ganti nama infrastruktur ke `onesis`, pisahkan situs live dari folder kerja | Belum dimulai | | |
| 28 | API | Penolakan pelamar (#14) | Terhambat | | Menunggu jawaban legal pertanyaan 13 |
| 29 | FE | Aplikasi publik: halaman publik, akun, area pencari kerja | Terhambat | | Menunggu 25 |
| 30 | BE | Isi `.env` produksi | Terhambat | | Menunggu SMTP dan domain produksi |
| 31 | BE | Instal Janus dan coturn (video interview) | Belum dimulai | | |
| 32 | API | Pengalaman kandidat dan agenda (#15) | Terhambat | | Menunggu 28. Perlu keputusan: tetap masuk go-live atau tidak |
| 33 | API | Rilis API untuk go-live | Terhambat | | Menunggu 10, 11, 16, 21, 24 (dan 32 kalau tetap masuk go-live) |
| 34 | FE | Back office perusahaan: akun, rekrutmen, halaman ERP | Terhambat | | Menunggu 16 dan 25 |
| 35 | FE | Aplikasi admin dan area psikolog (termasuk laporan aktivasi) | Terhambat | | Menunggu 25 |
| 36 | FE | Siapkan perintah build dan rilis | Terhambat | | Memori server terbatas, jadi build satu aplikasi per kali |
| 37 | BE | Instal Asterisk (interview telepon) | Terhambat | | Menunggu SIP trunk |
| 38 | BE | Siapkan database produksi: backup, migrasi, seed, hak akses, admin pertama | Terhambat | | Menunggu 33 |
| 39 | BE | Jalankan scheduler, queue worker, cache; pasang monitor uptime | Terhambat | | Menunggu 38 |
| 40 | BE | Atur nginx produksi | Belum dimulai | | |
| 41 | FE | Build dan deploy ketiga aplikasi di domain dev, atur service dan nginx | Terhambat | | Dilakukan user |
| 42 | FE | Arahkan link di email dan notifikasi ke halaman front end | Terhambat | | Menunggu 41 |
| 43 | BE | Import final dari salinan data pada tanggal freeze | Terhambat | | Tanggal freeze belum ditetapkan |
| 44 | BE | Jalankan perintah setelah import (rollup analitik, facet pencarian pelamar) | Terhambat | | Menunggu 43 |
| 45 | FE | Deploy front end ke produksi | Terhambat | | Menunggu domain produksi |
| 46 | BE | Smoke test produksi | Terhambat | | Menunggu 26, 27, 30, 31, 37–40 dan 45 |
| 47 | BE | Kirim email aktivasi: staf, pemilik perusahaan, pilot 200 pencari kerja, lalu 500 per hari | Terhambat | | Selesainya tergantung jumlah akun pencari kerja |
| 48 | API | Terjemahan pesan error API ke bahasa Indonesia | Terhambat | | Setelah import final |
| 49 | BE | Audit performa: pengukuran dengan data nyata | Terhambat | | Setelah import final |
| 50 | BE | Audit performa: tes jumlah query | Terhambat | | Menunggu 49 |
| 51 | BE | Temuan review arsitektur 7, 8, 20 | Siap dikerjakan | | |
| 52 | BE | Temuan review arsitektur 9, 21 (refactor) | Terhambat | | Setelah import final |
| 53 | BE | Hapus catatan talent pool langsung, bukan menunggu proses malam | Belum dimulai | | Perlu desain kecil |
| 54 | API | Item akun dan halaman publik (#39) | Siap dikerjakan | | Setelah go-live, kalau front end memintanya |
| 55 | API | Penutupan rekrutmen untuk akun terhapus atau perusahaan yang ditangguhkan (#40) | Siap dikerjakan | | Sama seperti 54 |
| 56 | API | Eskalasi approval (#41) | Siap dikerjakan | | Sama seperti 54 |
| 57 | API | Pencegahan edit bersamaan (#42) | Siap dikerjakan | | Front end harus siap lebih dulu |
| 58 | API | Penggabungan data referensi (#43) | Siap dikerjakan | | |
| 59 | API | Akses support ke data perusahaan (#44) | Siap dikerjakan | | |
| 60 | API | Bukti penerimaan penawaran dan approval penawaran tenant internal (#12) | Terhambat | | Menunggu jawaban legal pertanyaan 12 |
| 61 | API | Serah terima ke modul Employee dan dokumen pra-onboarding (#13) | Belum dimulai | | Perlu sesi desain |
| 62 | API | Otomasi asesmen (#22) | Siap dikerjakan | | |
| 63 | API | Tools dan institusi mitra psikolog (#23, #24) | Terhambat | | Menunggu jawaban legal pertanyaan 11, 17, 19 |
| 64 | API | Privasi pencari kerja bagian 3 dan 4 (#25) | Siap dikerjakan | | |
| 65 | API | Retensi data admin perusahaan (#26) | Terhambat | | Menunggu jawaban legal pertanyaan 15 |
| 66 | API | Kandidat manual (#31) | Terhambat | | Menunggu jawaban legal pertanyaan 20 |
| 67 | API | Pemeriksaan pra-kerja (#32) | Terhambat | | Menunggu jawaban legal pertanyaan 21 dan 32 |
| 68 | API | Notifikasi WhatsApp (#33) | Terhambat | | Menunggu verifikasi Meta, jawaban legal pertanyaan 22, dan 67 |
| 69 | API | Manajemen user | Terhambat | | Setelah import final |
| 70 | API | Fitur marketplace yang dibekukan (direktori perusahaan, registrasi perusahaan, detail kursus, discovery lowongan bagian 6–7) | Terhambat | | Setelah inti ERP TA |
