# Status porting API (Laravel 10 → 13, DDD)

Semua **92 operasi API** dari aplikasi lama sudah ada (+ `GET /user`). 277 tes, Pint dan PHPStan bersih.

| Context | Endpoint | Catatan |
|---|---|---|
| ReferenceData | 16 | |
| Identity | 8 | |
| CandidateProfile | 11 | |
| Company | 20 | |
| Recruitment/JobBoard | 10 | |
| Recruitment/JobManagement | 11 | |
| Billing | 4 | |
| Content | 10 | |
| Notification | 5 | `send-notification-all-users` praktis nonaktif untuk token API (admin belum bisa punya token) |

## Perbedaan dari aplikasi lama (ringkas; detail ada di laporan tiap context)

**Perbaikan keamanan** — resume, lowongan, group, kategori bookmark, invoice kini dicek kepemilikannya
(sebelumnya IDOR); `/company/plan-limit` tidak lagi membocorkan plan perusahaan lain; filter `lat/long`
memakai bound parameter (sebelumnya SQL injection); `/password/email` tidak mengirim kode di production;
detail kandidat/perusahaan tidak membocorkan kolom sensitif; password tidak lagi dikirim di email sambutan.

**Endpoint yang rusak di aplikasi lama dan kini berfungsi** — `/localizations/{locale}/strings`,
`POST /comment/{slug}/store`, `GET /candidates`, `/company/account-progress` step 4, `POST /contact` (tidak menyimpan),
`/company/job/*` (error dibungkus HTTP 200).

**Bentuk respons yang berubah** (karena respons lama rusak/`{}`): contact, comment, push notification
(`{success_count, failure_count}`), serta `store/edit/update/clone job` yang kini `{data:{data:...}}`
mengikuti maksud kode lama — **konfirmasi dengan klien**.

**Stateless** — bahasa/negara/mata uang lewat header, bukan session.

**Validasi & status code lebih ketat** — error validasi kini 422 `{message, errors}`; aturan status lowongan
(pending→active hanya jika auto-approve, dll.) dan kuota badge diberlakukan.

## Utang teknis / pekerjaan lanjutan

1. **Penulisan lintas-context** yang perlu dipindah ke kontrak di `Shared`:
   Identity menulis `candidates`/`companies`/`contact_infos` saat registrasi (hook `User::created` lama);
   Company menulis `applied_jobs` dan `user_plans` (kuota CV); JobManagement menulis `user_plans` (kuota badge);
   beberapa context menulis tabel `notifications` langsung → kontrak `Notifier`/`PlanQuota`.
2. **Google Indexing** saat lowongan aktif: `NullJobIndexer` (butuh `google/apiclient` + adapter).
3. **Job terjadwal** pembersih `candidate_cv_views` > 30 hari (dulu efek samping di `GET /candidates`).
4. **Admin API**: beri admin token (atau guard) agar `send-notification-all-users` dan bypass `pending job` admin bisa hidup.
5. **Mail**: konten email disederhanakan (Notification biasa) — template Blade lama tidak dibawa.
6. **URL tautan notifikasi** (admin/company) masih tebakan karena route web lama tidak ada di app baru.
7. Terjemahan `lang/en.json` perlu ditambah (`job_created_successfully`, `you_have_no_featured_job_limit`).
8. Perbarui `docs/openapi.yaml` dengan bentuk respons final (skema `data` masih garis besar) dan tambahkan `GET /user`.
9. Uji kontrak terhadap app lama (shadow traffic / perbandingan respons) di UAT sebelum cutover — semua tes saat ini
   diturunkan dari membaca kode, bukan dari menjalankan app lama.
10. Tes dijalankan di MariaDB berskema lama; siapkan DB `test_jobportal` permanen untuk CI.
