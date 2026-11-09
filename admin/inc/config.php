<?php
/* =========================================================
   inc/db.php  |  Database connection (PDO)
   Change the four values below to match your server.
   ========================================================= */

define('DB_HOST', getenv('YIG_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('YIG_DB_NAME') ?: 'young_india_group');
define('DB_USER', getenv('YIG_DB_USER') ?: 'root');
define('DB_PASS', getenv('YIG_DB_PASS') ?: '');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}