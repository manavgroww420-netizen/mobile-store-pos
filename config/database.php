<?php
declare(strict_types=1);

/**
 * Mobile Store Management System — POS & Inventory Platform
 * Database Configuration
 *
 * File: config/database.php
 *
 * Designed for standard Apache / MySQL / MariaDB environments.
 * Set database credentials using .env file or server environment variables.
 */

return [
    'host'      => getenv('DB_HOST') ?: '127.0.0.1',
    'port'      => (int)(getenv('DB_PORT') ?: 3306),
    'dbname'    => getenv('DB_NAME') ?: 'mobile_pos_db',
    'username'  => getenv('DB_USER') ?: 'root',
    'password'  => getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',

    // Strict PDO Connection Attributes
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Enforce native prepared statements
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+05:30'",
    ],
];
