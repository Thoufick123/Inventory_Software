<?php
/**
 * Database Configuration
 * Returns PDO connection parameters based on environment.
 */

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$initCommandAttribute = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
    ? constant('Pdo\\Mysql::ATTR_INIT_COMMAND')
    : PDO::MYSQL_ATTR_INIT_COMMAND;
$options[$initCommandAttribute] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))";

return [
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'grm_inventory'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    'options'  => $options,
];
