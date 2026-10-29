# Job Portal (Laravel 13, DDD)

Job portal built with Laravel 13 using a Domain-Driven Design layout (`src/`).
Code is organized by bounded context, each with Domain, Application, Infrastructure and Interface layers.

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate   # then set the DB_* values
php artisan serve
```

## Quality

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

## Documentation

Start with `docs/HANDOFF.md` (status and next step). Design decisions are in `docs/design/` (Phase 8 order: `phase-8-index.md`), API document rules and the error catalog in `docs/api/`, legacy analysis and gap audits in `docs/analysis/`, the go-live list in `docs/deployment-checklist.md`. Working rules for contributors and Claude: `CLAUDE.md`.
