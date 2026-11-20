#!/bin/bash
set -e

# Default to internal MariaDB if DB_HOST is localhost or 127.0.0.1
if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "127.0.0.1" ] || [ "$DB_HOST" = "localhost" ]; then
    echo "Starting internal MariaDB service..."
    service mariadb start

    DB_NAME="${DB_NAME:-mobile_pos_db}"
    echo "Configuring permissions and checking database $DB_NAME..."
    
    # Allow root connection from 127.0.0.1 with empty password
    mysql -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION; FLUSH PRIVILEGES;" 2>/dev/null || true

    INIT_MARKER="/var/lib/mysql/.amh_db_initialized"
    if [ ! -f "$INIT_MARKER" ]; then
        echo "Creating database $DB_NAME and importing schema..."
        mysql -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        mysql -u root -h 127.0.0.1 "$DB_NAME" < /var/www/html/sql/schema.sql
        mysql -u root -h 127.0.0.1 "$DB_NAME" < /var/www/html/sql/seed.sql
        touch "$INIT_MARKER"
        echo "Database schema and seeds imported successfully."
    fi
fi

# Ensure storage directories exist and are writable
mkdir -p /var/www/html/storage/logs /var/www/html/storage/cache
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

exec "$@"
