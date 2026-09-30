#!/bin/sh
set -e

: "${DB_HOST:=mysql}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=taskflow}"
: "${DB_USERNAME:=taskflow}"
: "${DB_PASSWORD:=secret}"

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent >/dev/null 2>&1; do
    sleep 2
done
echo "MySQL is up."

php artisan migrate --force

# Only seed the demo dataset the first time the database is empty, so that
# restarting the stack (with the mysql volume already populated) is a no-op.
USER_COUNT=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" -N -B \
    -e "SELECT COUNT(*) FROM \`${DB_DATABASE}\`.users;" 2>/dev/null || echo 0)

if [ "$USER_COUNT" = "0" ]; then
    echo "Seeding database with demo data..."
    php artisan db:seed --force
fi

exec "$@"
