# Move from MySQL to PostgreSQL 16 (designed 2026-10-10, step 1 coded and checked)

The application database moves from MySQL (MariaDB) to PostgreSQL 16. Reasons (user): PostgreSQL features and performance.
Two steps, in this order: **step 1 the port** (this document), then **step 2 selective cross-context foreign keys** (designed after step 1 is green).

## Confirmed by the user (2026-10-10)

- **Data:** nothing in `jobportal_v2` has to be kept (the `karir.pkp` data is test data). The new database starts from `migrate` on an empty PostgreSQL database; the legacy data is imported again later, when the real production data is mapped. The MySQL migrations `000095` to `000100` and the three business intelligence commands are **not** run on MySQL.
- **Server:** PostgreSQL 16, `pdo_pgsql` installed.
- **Legacy source:** stays MariaDB. `config/database.php` already has a separate `legacy` connection (read-only account) used only by `src/LegacyImport`; the default connection becomes `pgsql`. The server then needs both `pdo_pgsql` and `pdo_mysql`.
- **Letter case (decided 2026-10-10, user):** the application keeps normalising emails and slugs to lower case (it already does at every entry point). Human-typed text that must be unique ignoring case (stage name per company, screening option label per question) gets a unique **functional index on `lower(column)`**. No `citext` extension.
- **Search (decided 2026-10-10, user):** `like` becomes `ILIKE`, helped by **`pg_trgm` GIN indexes** and the `unaccent` extension, for the job title, the company name and the candidate search. The API behaviour does not change; the search is only faster. Ranked full-text search (`tsvector`) is a later, separate design.
- **Medians (decided 2026-10-10, user):** moving the live duration medians of the business intelligence to SQL (`percentile_cont`) is a separate small step **after** the port is green, with the same tests as the comparison.

## What changes in code

- **Connection:** `DB_CONNECTION=pgsql` in `.env.example`, `.env` and `.env.testing`; the test database is `jobportal_v2_test` on PostgreSQL; `TestDatabaseGuard` and `test:database:migrate` stay as they are (they only check the database name and refuse a prefix).
- **Extensions:** one migration first in order creates `pg_trgm` and `unaccent` (`CREATE EXTENSION IF NOT EXISTS`). The database account needs the right to create them (or a database administrator creates them once).
- **JSON access:** `JSON_UNQUOTE(JSON_EXTRACT(...))` (5 files: `DatabaseMasterRepository`, `DatabaseTagRegistry`, `DatabaseInAppNotifier`, `DatabaseRecruiterApplicationReader`) becomes the Laravel JSON path syntax (`name->en`), which works on the query builder grammar of both drivers. The `utf8mb4_unicode_ci` collation constants go away.
- **Check constraints:** the 41 `ALTER TABLE ... ADD CONSTRAINT ... CHECK` statements written with backticks are rewritten so the identifier quoting comes from the connection grammar.
- **`like` searches (about 13):** `ILIKE` through a small helper in `Shared/Infrastructure` so the escaping of `%`, `_` and `\` stays in one place.
- **Ordering of NULL:** PostgreSQL puts NULL last for ascending and first for descending, the opposite of MySQL for ascending. Every `orderBy` on a column that can be NULL gets an explicit `NULLS FIRST` or `NULLS LAST` that keeps today's order (for example `end_date is null desc` in the work experience list).
- **Strict `GROUP BY`:** every `selectRaw` with a non-aggregated column is checked; the rollups of the business intelligence (`insert ... select`) are read again.
- **Unsigned integers, enums:** none are used beyond `unsignedBigInteger`-style columns, which map to plain `bigint` / `smallint` with the existing check constraints; no change expected.
- **Test suite:** the whole suite runs on PostgreSQL; failures are fixed in the code, not by loosening a test.

## Order of work

1. Connection and extensions, test database created empty on PostgreSQL.
2. Migrations (quoting, `lower()` indexes, trigram indexes).
3. Queries (JSON, `ILIKE`, NULL order, `GROUP BY`).
4. The user runs `php artisan test:database:migrate --env=testing`, then `composer check`; fix until green.
5. The user approves; commit; the real database `jobportal_v2` is created on PostgreSQL and migrated.

## Open points to settle while coding

- Which columns get a trigram index (decided from the real `ILIKE` calls found in step 3).
- Whether the database account may create extensions (otherwise the administrator runs two `CREATE EXTENSION` statements once).

## What was coded (2026-10-10)

- `database/migrations/2026_10_03_000000_enable_postgres_extensions.php` (extensions and `immutable_unaccent`), `2026_10_10_000101_add_text_search_indexes.php` (trigram indexes on job title, company name and slug, profile headline and summary, experience job title, screening question text).
- Existing migrations were edited in place (the database is empty): `json` is `jsonb`; CHECK statements use double quotes; the stage name and the screening option label are unique through `lower()` indexes; the default stage per type is a partial unique index (the generated column is gone); `000044` adds `>= 0` checks instead of unsigned columns (PostgreSQL has none).
- `Shared/Infrastructure/Persistence/TextSearch` (all `like` searches, ignoring case and accents) and `NullOrder` (NULLs first when ascending, last when descending, as before); JSON reads use `->>`; the Jakarta day SQL is `CAST(column + INTERVAL '7 hours' AS date)`; `insertUsing` and the alert e-mail counter were rewritten for PostgreSQL.
- The `pgsql` connection sets the session time zone to UTC. `DB_CONNECTION` defaults to `pgsql`.
- Not changed: the `legacy` connection (MariaDB), the legacy tests (synthetic tables on the default connection with a prefix).
