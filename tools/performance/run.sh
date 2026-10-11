#!/usr/bin/env bash
# Fills a rehearsal database with synthetic data. Usage: tools/performance/run.sh <database>
# The database must be migrated and seeded (`DB_DATABASE=<name> php artisan migrate && php artisan db:seed`), and its name must end in `_perf`.
set -euo pipefail

database="${1:?usage: run.sh <database ending in _perf>}"
[[ "$database" == *_perf ]] || { echo "Refusing: the database name must end in _perf." >&2; exit 1; }

cd "$(dirname "$0")/../.."
set -a && . ./.env && set +a

export PGPASSWORD="$DB_PASSWORD"
count=$(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$database" -Atc "select count(*) from identity_users")
[[ "$count" == "0" ]] || { echo "Refusing: $database already has $count users." >&2; exit 1; }

psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$database" -f tools/performance/synthetic-data.sql
