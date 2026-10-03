# Job Portal API (Laravel 13, DDD)

Port API `karir.pkp` (Laravel 10) dengan arsitektur DDD-lite. Lihat:
- `docs/ARCHITECTURE.md` — struktur, konvensi, perbedaan disengaja
- `docs/PORTING_STATUS.md` — status, perbedaan dari app lama, utang teknis
- `docs/openapi.yaml` — kontrak API (Swagger)

## Menjalankan
```bash
composer install
cp .env.example .env && php artisan key:generate   # isi DB_* ke database berskema lama
php artisan serve
```
Database memakai skema yang sama dengan aplikasi lama; `database/schema/mysql-schema.sql` hanya untuk tes/dev lokal.
Isi `storage/firebase_credentials.json` (atau `FIREBASE_CREDENTIALS`) untuk FCM & social login.

## Tes & kualitas
```bash
# DB MySQL/MariaDB kosong bernama test_jobportal, muat skema dulu:
mysql test_jobportal < database/schema/mysql-schema.sql
DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=... DB_PASSWORD=... php artisan test
vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G
```
