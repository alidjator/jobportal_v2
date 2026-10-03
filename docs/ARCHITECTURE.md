# Arsitektur

Port API Job Portal (Laravel 10, aplikasi lama `/usr/share/nginx/html/karir.pkp`) ke Laravel 13
dengan pendekatan DDD "lite". **Kontrak API dipertahankan**: URL, body request, dan bentuk
respons sama dengan aplikasi lama (lihat `docs/openapi.yaml`). Database sama (skema acuan:
`database/schema/mysql-schema.sql`); aplikasi baru memetakan tabel lama, tidak membuat ulang.

## Bounded context (`src/`)

| Context | Tanggung jawab |
|---|---|
| Identity | login, register, social login, reset password, token Sanctum, `/profile`, `/user-info` |
| CandidateProfile | profil kandidat, resume, settings, daftar & detail publik kandidat |
| Company | profil perusahaan, account progress, bookmark kandidat, application group, daftar & detail publik perusahaan |
| Recruitment | lowongan (publik, kandidat, perusahaan), lamaran, bookmark lowongan, job alert, home |
| Billing | plan/pricing, plan perusahaan, transaksi, invoice PDF |
| Content | blog, komentar, FAQ, halaman CMS, kontak, about, page-list |
| Notification | notifikasi, device token FCM, push |
| ReferenceData | kategori, role, tipe, pengalaman, pendidikan, profesi, tag, negara, mata uang, bahasa, lokalisasi |
| Shared | kernel: `ApiResponse`, exception domain, `RequestContext`, `TranslatedNames`, base provider |

## Struktur per context

```
src/<Context>/
  Domain/          aturan bisnis murni: entity, value object, enum, interface repository, domain service
  Application/     use case (1 kelas invokable per use case: Commands/ dan Queries/), DTO
  Infrastructure/  implementasi repository (Eloquent/Query Builder), adaptor eksternal (Firebase, PDF, storage),
                   <Context>ServiceProvider (binding interface → implementasi)
  Interface/Http/  Controllers (tipis), Requests (FormRequest), Resources, routes.php
```

Aturan dependensi: `Interface → Application → Domain`; `Infrastructure → Domain/Application`.
Domain tidak boleh memakai Eloquent, Request, atau facade HTTP. Context tidak saling mengimpor kelas
internal; komunikasi lewat kontrak di `Shared/Application/Contracts` (contoh `UserProfileProjection`).
Model Eloquent hidup di `Infrastructure/Persistence/Eloquent` dan **tidak bocor keluar Infrastructure**.
Pembacaan (query) lintas-context pada tabel context lain hanya boleh read-only lewat Query Builder
di repository/read-model, dengan komentar yang menyebut tabel & alasannya.

## Konvensi

- **Respons**: selalu lewat `Src\Shared\Interface\Http\ApiResponse` — sukses `{data: ...}`, error `{error: "..."}`
  (400/401/403/404), validasi 422 `{message, errors}` (FormRequest). Bentuk isi `data` harus identik dengan aplikasi lama.
- **Error**: lempar `EntityNotFound` / `BusinessRuleViolation` / `AccessDenied` dari Application/Domain;
  pemetaan ke HTTP ada di `bootstrap/app.php`.
- **Stateless**: tidak ada session. Bahasa/negara/mata uang datang dari header (`X-Language`/`Accept-Language`,
  `selected_country`/`X-Country`, `X-Currency`) → `RequestContext`.
- **Terjemahan** (`*_translations`): pakai `Shared\Infrastructure\Persistence\TranslatedNames`.
- **Auth**: `auth:sanctum`, ambil user dari `$request->user()`. Role company: middleware `api_company`.
- **Tanpa helper global** (`setting()`, `currentLangCode()`, …); bungkus sebagai service yang di-inject.
- **Upload file**: inject `Shared\Application\Contracts\FileStorage` (`storeFile`, `storeImage`, `delete`); path yang dikembalikan relatif ke `public/` dan disimpan di DB seperti aplikasi lama.
- **Firebase**: `config/firebase.php` (credentials default `storage/firebase_credentials.json`); bungkus di balik interface domain agar bisa di-fake di test.
- **Controller** hanya: validasi (FormRequest) → panggil use case → `ApiResponse`.
- **Route**: `Interface/Http/routes.php` (atau `routes.<nama>.php`) dimuat otomatis dengan prefix `api` oleh `ContextServiceProvider`.
- **Test**: tiap endpoint punya feature test (`tests/Feature/<Context>`), pakai `Tests\FeatureTestCase`
  (transaksi di DB MySQL berskema lama). Jalankan:
  `DB_HOST=127.0.0.1 DB_PORT=<port> DB_USERNAME=<user> DB_PASSWORD=<pw> php artisan test`
  terhadap database `test_jobportal` (muat `database/schema/mysql-schema.sql`). **Jangan** arahkan test ke DB produksi.
- **Kualitas**: `vendor/bin/pint` dan `vendor/bin/phpstan analyse` harus bersih.

## Perbedaan yang disengaja dari aplikasi lama

| Endpoint | Perubahan |
|---|---|
| `/change-language/{code}`, `/change-country/{id}`, `/change-currency/{code}` | tidak lagi menyimpan state di session (stateless); klien mengirim header |
| `/localizations/{locale}/strings` | aplikasi lama error 500 (`successResponse` tidak ada); sekarang `{data: {...}}` |
| `/password/email` | `data.code` hanya muncul di `APP_ENV` local/testing |
| `/send-notification-all-users` | hanya admin |
