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
