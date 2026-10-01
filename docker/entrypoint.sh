#!/bin/sh
set -e

: "${DB_HOST:=mysql}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=taskflow}"
: "${DB_USERNAME:=taskflow}"
: "${DB_PASSWORD:=secret}"

# Wait for MySQL using PHP's own PDO/mysqlnd driver (the same one Laravel uses),
# rather than the mariadb-client CLI tools, which don't support MySQL 8's default
# caching_sha2_password authentication plugin.
db_ready() {
    php -r '
        try {
            new PDO(
                "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT"),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    '
}

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until db_ready; do
    sleep 2
done
echo "MySQL is up."

php artisan migrate --force

# Only seed the demo dataset the first time the database is empty, so that
# restarting the stack (with the mysql volume already populated) is a no-op.
USER_COUNT=$(php -r '
    try {
        $pdo = new PDO(
            "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        echo (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Throwable $e) {
        echo -1;
    }
')

if [ "$USER_COUNT" = "0" ]; then
    echo "Seeding database with demo data..."
    php artisan db:seed --force
fi

exec "$@"
