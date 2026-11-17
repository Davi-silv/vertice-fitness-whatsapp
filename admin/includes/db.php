<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }

    $needsSchema = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($needsSchema) {
        $schema = file_get_contents(ROOT_PATH . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Não foi possível ler database/schema.sql');
        }
        $pdo->exec($schema);
    }

    return $pdo;
}
