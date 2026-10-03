<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv('BISTRO_DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('BISTRO_DB_USER') ?: 'root';
$dbPassword = getenv('BISTRO_DB_PASSWORD') ?: '';
$dbName = getenv('BISTRO_DB_NAME') ?: 'bistro_db';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
$conn->set_charset('utf8mb4');
