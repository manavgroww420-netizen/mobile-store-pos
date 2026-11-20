#!/bin/bash
set -e

# Default to internal MariaDB if DB_HOST is localhost or 127.0.0.1
if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "127.0.0.1" ] || [ "$DB_HOST" = "localhost" ]; then
    echo "Starting internal MariaDB service..."
    service mariadb start

    DB_NAME="${DB_NAME:-mobile_pos_db}"
    DB_USER="${DB_USER:-amh_user}"
    DB_PASS="${DB_PASS:-amh_secure_pass}"
    echo "Configuring permissions and checking database $DB_NAME..."
    
    # Secure administrative setup over root Unix socket (no passwordless TCP root account)
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '${DB_USER}'@'127.0.0.1'; FLUSH PRIVILEGES;"

    INIT_MARKER="/var/lib/mysql/.init_${DB_NAME}_completed"
    if [ ! -f "$INIT_MARKER" ] || ! mysql "$DB_NAME" -e "SELECT 1 FROM \`users\` LIMIT 1;" 2>/dev/null; then
        echo "Importing database schema and seeds for $DB_NAME..."
        mysql "$DB_NAME" < /var/www/html/sql/schema.sql
        mysql "$DB_NAME" < /var/www/html/sql/seed.sql
        touch "$INIT_MARKER"
        echo "Database schema and seeds imported successfully."
    fi
fi

# Ensure storage directories exist and are writable
mkdir -p /var/www/html/storage/logs /var/www/html/storage/cache
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

exec "$@"
