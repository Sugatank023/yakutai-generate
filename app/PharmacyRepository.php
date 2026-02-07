<?php

declare(strict_types=1);

final class PharmacyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM pharmacies ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
        return $row === false ? [] : $row;
    }

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
