#!/bin/sh
# MADAM CAKES — container entrypoint.
# Prepares writable volumes, waits for the database, then hands over to Apache.
set -eu

mkdir -p /var/www/html/logs /var/www/html/assets/uploads
# Named volumes are created as root on first mount; bind mounts may refuse chown.
if ! chown -R www-data:www-data /var/www/html/logs /var/www/html/assets/uploads 2>/dev/null; then
    echo "madam-cakes: could not change ownership of logs/uploads; continuing." >&2
fi

if [ "${MADAM_WAIT_FOR_DB:-1}" = "1" ]; then
    echo "madam-cakes: waiting for database ${MADAM_DB_HOST:-db}:${MADAM_DB_PORT:-3306} ..."
    php -r '
        $host = getenv("MADAM_DB_HOST") ?: "db";
        $port = getenv("MADAM_DB_PORT") ?: "3306";
        $name = getenv("MADAM_DB_NAME") ?: "madam_cakes_db";
        $user = getenv("MADAM_DB_USER") ?: "root";
        $pass = getenv("MADAM_DB_PASS") ?: "";
        $dsn  = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", $host, $port, $name);

        for ($try = 1; $try <= 60; $try++) {
            try {
                new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                fwrite(STDOUT, "madam-cakes: database ready.\n");
                exit(0);
            } catch (Throwable $e) {
                sleep(2);
            }
        }

        fwrite(STDERR, "madam-cakes: database not reachable after 120 seconds.\n");
        exit(1);
    '
fi

exec "$@"
