<?php
return function() {
    $server   = $_ENV['DB_SERVER'];
    $database = $_ENV['DB_DATABASE'];
    $user     = $_ENV['DB_USER'];
    $pass     = $_ENV['DB_PASS'];
    $dsn      = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1";

    return function () use ($dsn, $user, $pass): PDO {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
        ]);
    };
};