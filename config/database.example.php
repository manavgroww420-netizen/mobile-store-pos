<?php
declare(strict_types=1);

/**
 * Mobile Store Management System — POS & Inventory Platform
 * Database Configuration Example Template
 *
 * File: config/database.example.php
 */

return [
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'dbname'    => 'mobile_store_db',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',

    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+05:30'",
    ],
];
