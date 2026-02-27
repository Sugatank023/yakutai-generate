<?php

// [ADDED] このRepositoryは pharmacies テーブルの先頭1件を「薬局情報」として扱う前提で取得・更新を行う。
// [ADDED] update() は既存1件の有無で INSERT/UPDATE を切り替える実装（Upsert相当）で、画面/APIの両方から利用される。

declare(strict_types=1);

final class PharmacyRepository
{
    // [ADDED] 依存: PDO。副作用は各メソッドのSQL実行時のみ。
    public function __construct(private readonly PDO $pdo)
    {
    }

    // [ADDED] 用途: 薬局情報として pharmacies の先頭1件を返す。
    // [ADDED] SQL目的: ORDER BY id ASC LIMIT 1 のSELECT。0件時は空配列を返す。
    public function get(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM pharmacies ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
        return $row === false ? [] : $row;
    }

    // [ADDED] 用途: 薬局情報を更新する（0件ならINSERT、既存ありならUPDATE）。
    // [ADDED] SQL目的: 実行時分岐で INSERT INTO pharmacies ... または UPDATE pharmacies ... WHERE id = :id。
    // [ADDED] 副作用: DB更新（created_at/updated_at または updated_at）。
    public function update(array $data): bool
    {
        $existing = $this->get();
        $now = date('c');

        if ($existing === []) {
            $stmt = $this->pdo->prepare('INSERT INTO pharmacies (pharmacy_name, address, phone, fax, created_at, updated_at)
                                         VALUES (:pharmacy_name, :address, :phone, :fax, :created_at, :updated_at)');
            return $stmt->execute([
                ':pharmacy_name' => $data['pharmacy_name'],
                ':address' => $data['address'],
                ':phone' => $data['phone'],
                ':fax' => $data['fax'],
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        $stmt = $this->pdo->prepare('UPDATE pharmacies
            SET pharmacy_name = :pharmacy_name,
                address = :address,
                phone = :phone,
                fax = :fax,
                updated_at = :updated_at
            WHERE id = :id');

        return $stmt->execute([
            ':id' => (int)$existing['id'],
            ':pharmacy_name' => $data['pharmacy_name'],
            ':address' => $data['address'],
            ':phone' => $data['phone'],
            ':fax' => $data['fax'],
            ':updated_at' => $now,
        ]);
    }
}
