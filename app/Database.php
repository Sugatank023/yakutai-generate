<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $databaseDir = __DIR__ . '/../database';
        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0777, true);
        }

        $dbPath = $databaseDir . '/app.sqlite';
        self::$pdo = new PDO('sqlite:' . $dbPath);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::initializeSchema(self::$pdo);

        return self::$pdo;
    }

    private static function initializeSchema(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('schema.sql の読み込みに失敗しました。');
        }

        $pdo->exec($schema);

        $count = (int)$pdo->query('SELECT COUNT(*) FROM pharmacies')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO pharmacies (pharmacy_name, address, phone, fax, created_at, updated_at)
                 VALUES (:name, :address, :phone, :fax, :created_at, :updated_at)'
            );
            $now = date('c');
            $stmt->execute([
                ':name' => 'サンプル薬局',
                ':address' => '東京都サンプル区1-2-3',
                ':phone' => '03-0000-0000',
                ':fax' => '03-0000-0001',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }
}
